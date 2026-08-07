<?php

namespace App\Services;

use Carbon\Carbon;
use Smalot\PdfParser\Parser;

/**
 * Parse une "Fiche de suivi de la progression" officielle (Inspection
 * Générale Pédagogique du Ministère des Enseignements Secondaire) : un PDF
 * contient une discipline, avec une fiche par promotion/classe.
 *
 * Ciblé sur ce format précis, pas un parseur générique de PDF. Le texte
 * extrait est bruyant (les exposants comme "1er"/"6e" sont scindés sur
 * deux lignes par l'extracteur), donc l'approche est heuristique par
 * conception : on vise les ~90% de lignes régulières, et les blocs mal
 * reconnus sont simplement absents de l'aperçu plutôt que de faire
 * planter tout l'import — c'est à l'écran d'aperçu (avant confirmation)
 * de laisser l'utilisateur repérer un manque.
 */
class CurriculumImportService
{
    private const MONTHS = [
        'SEPT' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
        'JANV' => 1, 'FEV' => 2, 'MARS' => 3, 'AVRIL' => 4,
        'MAI' => 5, 'JUIN' => 6, 'JUIL' => 7, 'AOUT' => 8,
    ];

    private const HOLIDAY_KEYWORDS = [
        'CONGES', 'CONGÉS', "PERIODE D'INTEGRATION", "PÉRIODE D'INTÉGRATION",
        'PRODUCTION SCOLAIRE', 'PRE-RENTREE', 'PRÉ-RENTRÉE', 'PRE-RENTRÉE',
    ];

    // "Activité n°1 :", "Activitén°7 :" (sans espace), "Sous-activité
    // n°4.1 :", "Sous –activité 4.9 :" (tiret cadratin, sans "n°") — les
    // documents réels mélangent ces variantes, d'où la tolérance large.
    private const NOTION_PATTERN =
        '/^(Sous[\s\-–]*activit[ée]s?|Activit[ée]s?)\s*n?°?\s*(\d+(?:\.\d+)?)\s*:?\s*(.*)$/ui';

    /**
     * @return array{discipline: ?string, annee_debut: int, promotions: array<int, array{promotion: string, weeks: array}>}
     */
    public function parse(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);

        $lines = [];
        foreach ($pdf->getPages() as $page) {
            foreach (explode("\n", $page->getText()) as $line) {
                $lines[] = trim(preg_replace('/\s+/u', ' ', $line));
            }
        }

        $anneeDebut = $this->detectSchoolYearStart($lines);

        // Découpage en sections par promotion, sur les lignes "Discipline"
        // / "Promotion". Une même fiche PDF ne change pas de discipline
        // (une discipline, plusieurs promotions), mais on relit
        // "Discipline :" à chaque section par robustesse.
        $sections = [];
        $discipline = null;
        $current = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (str_starts_with($line, 'Discipline')) {
                $discipline = $this->afterColon($line) ?: $discipline;
                continue;
            }

            if (str_starts_with($line, 'Promotion')) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $promotion = $this->readPromotionLabel($lines, $i);
                $current = ['promotion' => $promotion, 'lines' => []];
                continue;
            }

            if ($current !== null) {
                $current['lines'][] = $line;
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        $promotions = [];
        foreach ($sections as $section) {
            $promotions[] = [
                'promotion' => $section['promotion'],
                'weeks' => $this->parseWeeks($section['lines'], $anneeDebut),
            ];
        }

        return [
            'discipline' => $discipline,
            'annee_debut' => $anneeDebut,
            'promotions' => $promotions,
        ];
    }

    /**
     * "Promotion : 6" est souvent suivi d'une ligne isolée "e" (exposant
     * scindé par l'extracteur) — on les recolle sans espace ("6e"). Pour
     * "2des C et D" / "1res C et D", le suffixe ("des"/"res") se recolle
     * de la même façon, puis le reste ("C et D") suit sur une ou
     * plusieurs lignes, à recoller cette fois avec un espace, jusqu'à la
     * première ligne vide.
     */
    private function readPromotionLabel(array $lines, int $index): string
    {
        $label = trim($this->afterColon($lines[$index]));
        $j = $index + 1;

        if ($j < count($lines)) {
            $next = $lines[$j];
            if ($next !== '' && mb_strlen($next) <= 3 && preg_match('/^(e|er|re|des|res|ème|eme)$/ui', $next)) {
                $label .= $next;
                $j++;
            }
        }

        for ($k = $j; $k < $j + 3 && $k < count($lines); $k++) {
            if ($lines[$k] === '') {
                break;
            }
            $label .= ' ' . $lines[$k];
        }

        return trim($label);
    }

    private function afterColon(string $line): ?string
    {
        $pos = strpos($line, ':');
        return $pos === false ? null : trim(substr($line, $pos + 1));
    }

    /**
     * Année de rentrée (ex: 2025 pour "2025-2026"), utilisée pour
     * reconstruire une date complète à partir d'un simple "15 au 19" +
     * mois : septembre à décembre → année de rentrée, janvier à août →
     * année suivante.
     */
    private function detectSchoolYearStart(array $lines): int
    {
        foreach ($lines as $line) {
            if (preg_match('/(\d{4})\s*-\s*(\d{4})/', $line, $m)) {
                return (int) $m[1];
            }
        }
        return (int) date('Y');
    }

    private function yearFor(int $month, int $anneeDebut): int
    {
        return $month >= 9 ? $anneeDebut : $anneeDebut + 1;
    }

    private function trimesterFor(int $month, int $day): int
    {
        if ($month >= 9 || $month <= 3) {
            return $month >= 9 ? 1 : 2;
        }
        if ($month === 4) {
            return $day >= 16 ? 3 : 2;
        }
        return 3;
    }

    /**
     * @return array<int, array{trimester:int, period_start:string, period_end:string, situation_apprentissage:?string, activities_text:string, taux_prevu:float, is_teaching_week:bool}>
     */
    private function parseWeeks(array $lines, int $anneeDebut): array
    {
        $weeks = [];
        $currentMonth = null;
        $n = count($lines);
        $i = 0;

        while ($i < $n) {
            $line = $lines[$i];

            if ($line === '') {
                $i++;
                continue;
            }

            // Ligne mois isolée (transition entre deux mois).
            if (preg_match('/^([A-ZÉÛ]{3,6})$/u', $line, $m) && isset(self::MONTHS[$m[1]])) {
                $currentMonth = self::MONTHS[$m[1]];
                $i++;
                continue;
            }

            // Ancre de semaine : "SEPT 1-", "OCT 4-", ou juste "9-" si le
            // mois était sur la ligne précédente.
            if (preg_match('/^(?:([A-ZÉÛ]{3,6})\s+)?(\d{1,2})-\s*$/u', $line, $m)) {
                if ($m[1] !== '' && isset(self::MONTHS[$m[1]])) {
                    $currentMonth = self::MONTHS[$m[1]];
                }

                $block = $this->readWeekBlock($lines, $i + 1, $currentMonth, $anneeDebut);
                if ($block !== null) {
                    $weeks[] = $block['week'];
                    $i = $block['next_index'];
                    continue;
                }
            }

            // Semaine spéciale sur une seule ligne : "NOV 10 au 14
            // Période d'intégration".
            if (preg_match(
                '/^([A-ZÉÛ]{3,6})\s+(\d{1,2})\s+au\s+(\d{1,2})\s+(.+)$/u',
                $line,
                $m
            ) && isset(self::MONTHS[$m[1]]) && $this->looksLikeHoliday($m[4])) {
                $month = self::MONTHS[$m[1]];
                $currentMonth = $month;
                $year = $this->yearFor($month, $anneeDebut);

                $weeks[] = [
                    'trimester' => $this->trimesterFor($month, (int) $m[2]),
                    'period_start' => sprintf('%04d-%02d-%02d', $year, $month, (int) $m[2]),
                    'period_end' => sprintf('%04d-%02d-%02d', $year, $month, (int) $m[3]),
                    'situation_apprentissage' => null,
                    'activities_text' => trim($m[4]),
                    'notions' => [],
                    'taux_prevu' => 0,
                    'is_teaching_week' => false,
                ];
                $i++;
                continue;
            }

            $i++;
        }

        return $weeks;
    }

    /**
     * Reconnaît une ligne "Activité n°X : ..." ou "Sous-activité n°X.Y :
     * ..." — chacune devient une notion individuelle, sélectionnable par
     * l'enseignant, plutôt qu'un simple bloc de texte pour toute la
     * semaine.
     */
    private function matchNotion(string $text): ?array
    {
        $text = trim($text);
        if ($text === '' || !preg_match(self::NOTION_PATTERN, $text, $m)) {
            return null;
        }

        $kind = str_starts_with(mb_strtolower(trim($m[1])), 'sous') ? 'Sous-activité' : 'Activité';

        return ['label' => $kind . ' n°' . $m[2], 'text' => trim($m[3])];
    }

    private function looksLikeHoliday(string $text): bool
    {
        $upper = mb_strtoupper($text);
        foreach (self::HOLIDAY_KEYWORDS as $kw) {
            if (str_contains($upper, mb_strtoupper($kw))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Lit le bloc qui suit une ancre de semaine : plage de dates, label
     * SA, texte d'activités, puis le couple de taux (prévu/cumulé) qui
     * marque la fin du bloc. S'arrête aussi si une nouvelle ancre de
     * semaine est rencontrée avant tout taux (bloc mal formé — ignoré).
     */
    private function readWeekBlock(array $lines, int $start, ?int $month, int $anneeDebut): ?array
    {
        $n = count($lines);
        $i = $start;

        // Plage de jours "15 au 19", ou dates complètes sur deux lignes
        // "29/09 au" / "03/10".
        $dayStart = $dayEnd = null;
        $monthStart = $monthEnd = $month;

        while ($i < $n && $lines[$i] === '') {
            $i++;
        }

        if ($i < $n && preg_match('/^(\d{1,2})\s+au\s+(\d{1,2})\s*$/u', $lines[$i], $m)) {
            $dayStart = (int) $m[1];
            $dayEnd = (int) $m[2];
            $i++;
        } elseif ($i < $n && preg_match('/^(\d{1,2})\/(\d{1,2})\s+au\s*$/u', $lines[$i], $m)) {
            $dayStart = (int) $m[1];
            $monthStart = (int) $m[2];
            $i++;
            while ($i < $n && $lines[$i] === '') {
                $i++;
            }
            if ($i < $n && preg_match('/^(\d{1,2})\/(\d{1,2})\s*$/u', $lines[$i], $m2)) {
                $dayEnd = (int) $m2[1];
                $monthEnd = (int) $m2[2];
                $i++;
            }
        }

        if ($dayStart === null || $monthStart === null || $monthEnd === null) {
            // Bloc de date non reconnu : on abandonne cette semaine plutôt
            // que de produire une date fausse.
            return null;
        }

        // SA + activités/sous-activités numérotées, jusqu'au couple de
        // taux. Chaque "Activité n°X" / "Sous-activité n°X.Y" devient une
        // notion individuelle (avec son texte, qui continue parfois sur
        // plusieurs lignes) ; le texte hors numérotation part en repli
        // dans $textParts, au cas où une semaine ne suit pas ce format.
        $situation = null;
        $textParts = [];
        $notions = [];
        $currentNotion = null;
        $tauxPrevu = null;

        $pushCurrentNotion = function () use (&$currentNotion, &$notions) {
            if ($currentNotion !== null && trim($currentNotion['text']) !== '') {
                $notions[] = $currentNotion;
            }
            $currentNotion = null;
        };

        while ($i < $n) {
            $line = $lines[$i];

            if ($line === '') {
                $i++;
                continue;
            }

            // Nouvelle ancre de semaine rencontrée sans avoir trouvé de
            // taux : bloc incomplet, on s'arrête sans consommer cette
            // ligne (elle sera relue comme ancre par l'appelant).
            if (preg_match('/^(?:[A-ZÉÛ]{3,6}\s+)?\d{1,2}-\s*$/u', $line)) {
                break;
            }

            if (preg_match('/^SA\s*(\d+)\b\s*(.*)$/ui', $line, $m)) {
                $situation = 'SA ' . $m[1];
                $rest = trim($m[2]);
                $i++;
                if ($rest !== '') {
                    if ($notionMatch = $this->matchNotion($rest)) {
                        $pushCurrentNotion();
                        $currentNotion = $notionMatch;
                    } else {
                        $textParts[] = $rest;
                    }
                }
                continue;
            }

            // En-tête de regroupement ("PARTIE A" / "PARTIE B") — ignoré,
            // ce n'est pas une notion en soi.
            if (preg_match('/^PARTIE\s+[A-Z]\s*$/ui', $line)) {
                $i++;
                continue;
            }

            // Couple de taux (prévu / cumulé) — fin du bloc. Le second
            // nombre (cumulé) n'est jamais stocké ; parfois remplacé par
            // "---" sur une semaine interrompue, ignoré dans ce cas.
            if (preg_match('/^(\d+(?:[.,]\d+)?)\s+(\d+(?:[.,]\d+)?|-{1,3})\s*$/u', $line, $m)) {
                $tauxPrevu = (float) str_replace(',', '.', $m[1]);
                $i++;
                break;
            }

            if ($notionMatch = $this->matchNotion($line)) {
                $pushCurrentNotion();
                $currentNotion = $notionMatch;
                $i++;
                continue;
            }

            // Ligne de continuation : rattachée à la notion en cours si
            // une notion est ouverte, sinon au texte générique de repli.
            if ($currentNotion !== null) {
                $currentNotion['text'] = trim($currentNotion['text'] . ' ' . $line);
            } else {
                $textParts[] = $line;
            }
            $i++;
        }
        $pushCurrentNotion();

        $year = $this->yearFor($monthStart, $anneeDebut);
        $yearEnd = $this->yearFor($monthEnd, $anneeDebut);

        $activitiesText = !empty($notions)
            ? implode("\n", array_map(fn ($n) => $n['label'] . ' : ' . $n['text'], $notions))
            : trim(implode("\n", $textParts));

        return [
            'week' => [
                'trimester' => $this->trimesterFor($monthStart, $dayStart),
                'period_start' => sprintf('%04d-%02d-%02d', $year, $monthStart, $dayStart),
                'period_end' => sprintf('%04d-%02d-%02d', $yearEnd, $monthEnd, $dayEnd),
                'situation_apprentissage' => $situation,
                'activities_text' => $activitiesText,
                'notions' => $notions,
                'taux_prevu' => $tauxPrevu ?? 0,
                'is_teaching_week' => true,
            ],
            'next_index' => $i,
        ];
    }
}
