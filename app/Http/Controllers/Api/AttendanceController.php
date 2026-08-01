<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Site;
use App\Models\Schedule;
use App\Models\CurriculumWeek;

use Carbon\Carbon;

use App\Services\FirebaseService;
use App\Services\FaceRecognitionService;


class AttendanceController extends Controller
{


    /**
     * POINTAGE ARRIVEE / DEPART
     */
    public function checkIn(Request $request)
    {


        $request->validate([

            'attendance_type'=>'required|in:arrival,departure',

            'latitude'=>'required|numeric',

            'longitude'=>'required|numeric',

            'selfie_photo'=>'required',

            // Attestation de cours (cahier de texte) — absents pour un
            // pointage classique, comportement strictement inchangé.
            'schedule_id'=>'nullable|integer',

            'taux_realise'=>'nullable|numeric|min:0|max:100',

            'notes'=>'nullable|string'

        ]);



        $employee = $request->user();

        if(!$employee instanceof Employee){

            return response()->json([

                'success'=>false,

                'message'=>'Accès refusé'

            ],403);

        }



        $site = Site::find($employee->site_id);



        if(!$site){

            return response()->json([

                'success'=>false,

                'message'=>'Site introuvable'

            ],404);

        }



        /*
        Attestation de cours (cahier de texte) — uniquement si un
        schedule_id est fourni. Le pointage classique (schedule_id absent)
        n'entre jamais dans ce bloc : comportement strictement inchangé.
        */


        $schedule = null;
        $curriculumWeek = null;

        if ($request->filled('schedule_id')) {

            // Cherché dans les créneaux de l'entreprise courante
            // uniquement (Schedule est cloisonné), puis on vérifie en plus
            // que ce créneau appartient bien à CET employé : un enseignant
            // ne peut pas attester le cours d'un collègue.
            $schedule = Schedule::find($request->schedule_id);

            if (!$schedule || $schedule->employee_id !== $employee->id) {
                return response()->json([
                    'success'=>false,
                    'message'=>'Créneau invalide'
                ],422);
            }

            // `start_time` est une colonne TIME (ex: "15:00:00") :
            // Carbon::parse() d'une chaîne heure-seule prend la date du
            // jour par défaut, exactement ce qu'il faut ici.
            $courseStart = Carbon::parse($schedule->start_time);

            if (Carbon::now()->lessThan($courseStart)) {
                return response()->json([
                    'success'=>false,
                    'message'=>"Ce cours n'a pas encore commencé"
                ],422);
            }

            // Semaine de progression courante pour cette matière+classe,
            // déterminée côté serveur (jamais à partir d'une valeur
            // envoyée par le client) — absente si aucune progression n'a
            // encore été importée pour cette semaine : l'attestation reste
            // possible, juste sans lien avec le cahier de texte.
            $curriculumWeek = CurriculumWeek::where('subject_id', $schedule->subject_id)
                ->where('class_level_id', $schedule->class_level_id)
                ->where('is_teaching_week', true)
                ->whereDate('period_start', '<=', Carbon::today())
                ->whereDate('period_end', '>=', Carbon::today())
                ->first();
        }



        /*
        Vérification visage
        */


        if(!$employee->is_enrolled){


            return response()->json([

                'success'=>false,

                'message'=>'Visage non enregistré'

            ],400);

        }





        /*
        Vérification GPS
        */


        $distance = $this->calculateDistance(

            $request->latitude,

            $request->longitude,

            $site->latitude,

            $site->longitude

        );



        $insideZone = $distance <= $site->radius;



        if(!$insideZone){


            $status="outside_zone";


        }else{


            /*
            Vérification faciale (AWS Rekognition si configuré,
            sinon simulation en développement)
            */


            $faceResult = (new FaceRecognitionService())->compare(
                $employee->reference_photo,
                $request->selfie_photo
            );

            $score = $faceResult['score'];

            $threshold = config('services.rekognition.match_threshold', 80);



            if($score < $threshold){

                $status="face_failed";

            }else{

                $status="success";

            }


        }




        /*
        Retard
        */


        $late=false;


        if($schedule){

            // Attestation de cours : le retard se calcule sur l'heure du
            // créneau, pas sur l'horaire général du site.
            $limit = Carbon::parse($schedule->start_time)
                ->addMinutes($site->late_tolerance_minutes ?? 0);

            $late = Carbon::now()->greaterThan($limit);

        }elseif(

            $request->attendance_type=="arrival"

            &&

            $site->work_start_time

        ){


            $limit = Carbon::parse(

                $site->work_start_time

            )->addMinutes(

                $site->late_tolerance_minutes

            );


            $late = Carbon::now()->greaterThan($limit);


        }




        /*
        Création présence
        */


        $attendance = Attendance::create([


            'employee_id'=>$employee->id,


            'site_id'=>$site->id,


            'attendance_type'=>$request->attendance_type,


            'latitude'=>$request->latitude,


            'longitude'=>$request->longitude,


            'selfie_photo'=>$request->selfie_photo,


            'face_match_score'=>$score ?? 0,


            'is_inside_zone'=>$insideZone,


            'status'=>$status,


            'is_late'=>$late,


            'check_time'=>Carbon::now(),


            'schedule_id'=>$schedule?->id,


            'curriculum_week_id'=>$curriculumWeek?->id,


            'taux_realise'=>$schedule
                ? ($request->taux_realise ?? $curriculumWeek?->taux_prevu)
                : null,


            'notes'=>$schedule ? $request->notes : null,


        ]);






        /*
        Calcul temps travail
        */


        $workedMinutes=null;



        if(

            $request->attendance_type=="departure"

            &&

            $status=="success"

        ){


            $arrival = Attendance::where(

                'employee_id',

                $employee->id

            )

            ->where(

                'attendance_type',

                'arrival'

            )

            ->whereDate(

                'check_time',

                Carbon::today()

            )

            ->first();



            if($arrival){


                $workedMinutes = Carbon::parse(

                    $arrival->check_time

                )->diffInMinutes(

                    Carbon::now()

                );



                $attendance->update([

                    'work_minutes'=>$workedMinutes

                ]);


            }


        }






        return response()->json([


            'success'=>$status=="success",


            'status'=>$status,


            'attendance_type'=>$request->attendance_type,


            'distance'=>round($distance,2),


            'face_match_score'=>$score ?? 0,


            'attendance_id'=>$attendance->id,


            'worked_minutes'=>$workedMinutes,


            'is_late'=>$late,


            'schedule_id'=>$schedule?->id,


            'curriculum_week_id'=>$curriculumWeek?->id,



        ]);



    }






    /**
     * Vérification entrée zone
     * Notification mobile
     */
    public function checkZone(Request $request)
    {


        $request->validate([


            'latitude'=>'required|numeric',


            'longitude'=>'required|numeric'


        ]);




        $employee = $request->user();

        if(!$employee instanceof Employee){

            return response()->json([

                'success'=>false,

                'message'=>'Accès refusé'

            ],403);

        }



        $site = Site::find(

            $employee->site_id

        );



        $distance=$this->calculateDistance(

            $request->latitude,

            $request->longitude,

            $site->latitude,

            $site->longitude

        );




        $inside = $distance <= $site->radius;




        if(!$inside){


            return response()->json([


                'success'=>true,

                'inside_zone'=>false


            ]);


        }






        $already = Attendance::where(

            'employee_id',

            $employee->id

        )

        ->where(

            'attendance_type',

            'arrival'

        )

        ->whereDate(

            'check_time',

            Carbon::today()

        )

        ->exists();






        if($already){


            return response()->json([


                'success'=>true,

                'inside_zone'=>true,

                'message'=>'Présence déjà enregistrée'


            ]);

        }







        return response()->json([


            'success'=>true,


            'inside_zone'=>true,


            'notification'=>[


                "title"=>"Bonne arrivée au service 👋",


                "message"=>"Veuillez marquer votre présence avant de commencer votre journée."


            ]


        ]);



    }








    /**
     * Calcul distance GPS
     */
    private function calculateDistance(

        $lat1,

        $lon1,

        $lat2,

        $lon2

    ){


        $earth=6371000;



        $dLat=deg2rad($lat2-$lat1);


        $dLon=deg2rad($lon2-$lon1);



        $a=

        sin($dLat/2)**2

        +

        cos(deg2rad($lat1))

        *

        cos(deg2rad($lat2))

        *

        sin($dLon/2)**2;



        $c=2*atan2(

            sqrt($a),

            sqrt(1-$a)

        );



        return $earth*$c;


    }

/**
     * État du jour : l'employé a-t-il déjà pointé arrivée / départ aujourd'hui ?
     */
    public function todayStatus(Request $request)
    {
        $employee = $request->user();
        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé'
            ], 403);
        }

        $arrival = Attendance::where('employee_id', $employee->id)
            ->where('attendance_type', 'arrival')
            ->whereDate('check_time', Carbon::today())
            ->orderBy('check_time')
            ->first();

        $departure = Attendance::where('employee_id', $employee->id)
            ->where('attendance_type', 'departure')
            ->whereDate('check_time', Carbon::today())
            ->orderBy('check_time', 'desc')
            ->first();

        // Prochaine action possible : arrival, departure, ou done
        $next = 'arrival';
        if ($arrival && !$departure) {
            $next = 'departure';
        } elseif ($arrival && $departure) {
            $next = 'done';
        }

        return response()->json([
            'success'       => true,
            'next_action'   => $next,
            'arrival_time'  => $arrival ? Carbon::parse($arrival->check_time)->format('H:i') : null,
            'departure_time'=> $departure ? Carbon::parse($departure->check_time)->format('H:i') : null,
            'work_minutes'  => $departure ? $departure->work_minutes : null,
        ]);
    }
public function history(Request $request)
    {
        $employee = $request->user();
        if (!$employee instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé'
            ], 403);
        }

        $attendances = Attendance::where('employee_id', $employee->id)
            ->orderBy('check_time', 'desc')
            ->get();
 
        $jours = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
        $mois  = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
 
        $days = [];
 
        foreach ($attendances as $a) {
            $c = Carbon::parse($a->check_time);
            $key = $c->format('Y-m-d');
 
            if (!isset($days[$key])) {
                $label = $jours[$c->dayOfWeekIso - 1]
                    . ' ' . str_pad($c->day, 2, '0', STR_PAD_LEFT)
                    . ' ' . $mois[$c->month - 1];
 
                $days[$key] = [
                    'date'         => $key,
                    'label'        => $label,
                    'arrival'      => null,
                    'departure'    => null,
                    'is_late'      => false,
                    'work_minutes' => null,
                ];
            }
 
            if ($a->attendance_type === 'arrival') {
                $days[$key]['arrival'] = $c->format('H:i');
                $days[$key]['is_late'] = (bool) $a->is_late;
            } elseif ($a->attendance_type === 'departure') {
                $days[$key]['departure']    = $c->format('H:i');
                $days[$key]['work_minutes'] = $a->work_minutes;
            }
        }
 
        return response()->json([
            'success' => true,
            'days'    => array_values($days),
        ]);
    }
}