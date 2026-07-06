<?php
// Corrige l'erreur "404 Not Found" quand on visite http://localhost:8000/ directement :
// PHP cherche un index.php ou index.html à la racine, sinon il renvoie 404.
header('Location: login.php');
exit;