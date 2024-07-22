
<?php

include("database.php");


$dbTripsora = new DatabaseTripsora();

//$dbTripsora->create_database();

//$dbTripsora->create_cities_table();

//$dbTripsora->insert_cities_into_db();

$people;

$startDate = new DateTime("2024-8-19"); // travel start

$endDate = new DateTime("2024-8-22"); // travel end



$current = new DateTime(date('Y-m-d'));

$ndays = date_diff($endDate, $current)->days;

print_r($ndays);

// store the tour array
$tours_array = $dbTripsora->get_tours_by_rating('England', 'London', $ndays);

/*
foreach ($tours_array['tours'] as $tours) {
    print_r($tours);
    echo '<br>';
    echo '<br>';
}
*/

$events_array = $dbTripsora->filter_tours_by_date_range($tours_array, $startDate, $endDate);

print_r($events_array);
//$dbTripsora->show_tour_events(445);
