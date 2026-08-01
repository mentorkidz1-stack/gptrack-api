# GPTrack — API Laravel (projet intégré, prêt à lancer)

Ce projet contient déjà le correctif token Sanctum employé :
- app/Models/Employee.php          → trait HasApiTokens ajouté
- app/Http/Controllers/Api/AuthController.php → verifyOtp émet un token
Aucune édition manuelle requise. Le dossier vendor/ est inclus (pas besoin
de `composer install`).

## Étapes

1. Vérifier le fichier .env (base de données surtout) :
   DB_DATABASE, DB_USERNAME, DB_PASSWORD doivent pointer vers ta base MySQL.
   Si APP_KEY est vide :  php artisan key:generate

2. Créer / migrer la base (si ce n'est pas déjà fait) :
       php artisan migrate

3. Avoir au moins un employé de test rattaché à un site avec coordonnées GPS.
   Exemple via Tinker (adapter latitude/longitude à TON lieu réel) :
       php artisan tinker
       >>> $s = \App\Models\Site::create(['company_id'=>1,'name'=>'Siège','latitude'=>6.3703,'longitude'=>2.3912,'radius'=>200,'work_start_time'=>'08:00','late_tolerance_minutes'=>15]);
       >>> \App\Models\Employee::create(['company_id'=>1,'site_id'=>$s->id,'full_name'=>'Kofi Test','phone'=>'97000000','is_enrolled'=>false,'status'=>true]);

4. Lancer le serveur (—host=0.0.0.0 pour le joindre depuis un vrai téléphone) :
       php artisan serve --host=0.0.0.0 --port=8000

## Rappel sécurité (à traiter plus tard)
- request-otp renvoie encore l'OTP dans la réponse JSON (pratique en dev,
  à supprimer en prod + brancher un vrai SMS).
- check-in calcule le score facial avec rand(80,99) : à remplacer par une
  vraie comparaison de visages (prochaine étape).
