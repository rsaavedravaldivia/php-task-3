
<?php

include("database.php");


$dbTripsora = new DatabaseTripsora();

//$dbTripsora->create_database();

//$dbTripsora->create_cities_table();

//$dbTripsora->insert_cities_into_db();

//$dbTripsora->get_tours_by_names('England', 'London', 68);

$dbTripsora->show_tour_events(445);
