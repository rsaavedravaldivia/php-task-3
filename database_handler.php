
<?php

include("database.php");


$dbTripsora = new DatabaseTripsora();

//$dbTripsora->create_database();

//$dbTripsora->create_cities_table();

//$dbTripsora->insert_cities_into_db();

$people;

$startDate = date_create("2024-12-19"); // travel start
$endDate = date_create("2024-12-22"); // travel end

$ndays = date_diff($startDate, $endDate)->days;

// store the tour array
$tours_array = $dbTripsora->get_tours_by_rating('England', 'London', $ndays);

//$dbTripsora->show_tour_events(445);
