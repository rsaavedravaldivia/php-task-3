<?php

class DatabaseTripsora
{
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $dbName = "freetours_tripsora";
    private $accessToken;


    public function __construct()
    {
        $this->accessToken = $this->getAccessTokenFromURL("");
    }

    function get_curl_response($endpoint, $params)
    {

        $url = $endpoint;
        // Build query only if array is not 0
        if ($params !== 0) {
            $url = $endpoint . '?' . http_build_query($params);
        }
        // Initialize cURL session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json'
        ));
        // Execute cURL request
        $response = curl_exec($ch);

        // Check response
        if ($response === false) {
            echo "cURL error: " . curl_error($ch);
            curl_close($ch);
            return [];
        } else {
            // Decode the JSON response
            $response_data = json_decode($response, true);
            curl_close($ch);
            return $response_data;
        }
    }

    function getAccessTokenFromURL($url)
    {
        $data = file_get_contents($url);
        if ($data === false) {
            die('Error fetching data.');
        }
        $response = json_decode($data, true);
        if ($response === null) {
            die('Error decoding json.');
        }
        return $response['token'];
    }

    function get_free_tour_countries()
    {
        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/countries";
        $array = [];

        $response_data = $this->get_curl_response($endpoint, 0);

        if (isset($response_data['data'])) {
            foreach ($response_data['data'] as $country) {
                $array[] = [
                    'id' => $country['id'],
                    'name' => $country['title']['en']
                ];
            }
            return $array;
        } else {
            echo "No country data found in the response.";
            return [];
        }
    }

    function create_database()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password);
        if ($conn->connect_error) {
            die("Error connecting to db: " . $conn->connect_error);
        }
        try {
            $sql = "CREATE DATABASE $this->dbName";
            if ($conn->query($sql) === TRUE) {
                echo "Database created successfully";
            } else {
                echo "Error creating database: " . $conn->error;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            $conn->close();
        }
    }
    function create_cities_table()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        try {
            $sql = "CREATE TABLE cities (
                        country_id INT(6) NOT NULL,
                        city_id INT(6) UNIQUE NOT NULL,
                        country_name VARCHAR(50) NOT NULL,
                        city_name VARCHAR(50) NOT NULL
                    )";
            if ($conn->query($sql) === TRUE) {
                echo "Table created successfully";
            } else {
                echo "Error creating table: " . $conn->error;
            }
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            $conn->close();
        }
    }


    function get_cities_by_country_id($countryId, $haveActive = 0, $haveFree = 0)
    {
        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/cities/$countryId";
        $params = array(
            'haveActive' => $haveActive,
            'haveFree' => $haveFree
        );

        $response_data = $this->get_curl_response($endpoint, $params);
        if (isset($response_data['data'])) {
            return $response_data['data'];
        } else {
            echo "No city data found in the response.";
            return [];
        }
    }

    function insert_cities_into_db()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die("Connection error: " . $conn->connect_error);
        }
        echo "Connected successfully";

        $countriesArray = $this->get_free_tour_countries();

        $stmt = $conn->prepare("INSERT INTO cities (country_id, city_id, country_name, city_name) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $country_id, $city_id, $country_name, $city_name);

        try {
            foreach ($countriesArray as $country) {
                if (!isset($country['id'], $country['name'])) {
                    echo "Invalid country data: " . print_r($country, true);
                    continue; // Skip this country if data is invalid
                }

                $country_id = $country['id'];
                $country_name = $country['name'];

                $cities = $this->get_cities_by_country_id($country_id);

                foreach ($cities as $city) {

                    if (!isset($city['id'], $city['title']['en'])) {
                        echo "Invalid city data";
                        print_r($city);
                        echo "<br>";
                        continue; // Skip this city if data is invalid
                    }
                    $city_id = $city['id'];
                    $city_name = $city['title']['en'];
                    $stmt->execute();
                    print_r('ID and City added successfully <br>');
                    print_r($city_id);
                    echo '<br>';
                    print_r($city_name);
                    echo '<br>';
                }
            }
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        } finally {
            $stmt->close();
            $conn->close();
        }
    }

    function get_country_and_city_id_by_names($country_name, $city_name)
    {
        // search for city id and country id from database by names
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die('Connection error: ' . $conn->connect_error);
        }
        echo 'Connected successfully';

        $stmt = $conn->prepare('SELECT country_id, city_id FROM cities WHERE country_name = ? AND city_name = ?');

        $stmt->bind_param('ss', $country_name, $city_name);
        $stmt->execute();
        $stmt->bind_result($country_id, $city_id);

        if ($stmt->fetch()) {
            $stmt->close();
            $conn->close();

            echo $country_id . ' ' . $city_id;
            return array('country_id' => $country_id, 'city_id' => $city_id);
        } else {
            $stmt->close();
            $conn->close();
            echo 'No city or country found with that name.';
            return null;
        }
    }
    function get_tours_by_rating($country_name, $city_name, $ndays)
    {
        // Get the IDs of the country and city by their names
        $ids = $this->get_country_and_city_id_by_names($country_name, $city_name);

        // If IDs are not found, return an empty array
        if (!$ids) {
            echo 'Records not found.';
            return [];
        }

        $country_id = $ids['country_id'];
        $city_id = $ids['city_id'];

        // Define the endpoint URL and parameters
        $page = 1;
        $result = array('tours' => []);

        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/tours";



        while (true) {
            $params = array(
                'page' => $page,
                'bid' => 0,
                'cityId' => $city_id,
                'countryId' => $country_id,
                'onlyFree' => 0,
                'onlyPaid' => 0,
                'days' => $ndays
            );

            $response =  $this->get_curl_response($endpoint, $params);

            foreach ($response['data']['tours'] as $tour) {
                if ($tour['rating'] >= 3.5) {

                    $temp_array = array(
                        'id' => $tour['id'],
                        'title' => $tour['title']['en'],
                        'description' => $tour['description']['en'],
                        'price' => $tour['price']['value'],
                        'currency' => $tour['price']['currency'],
                        'length' => $tour['length'],
                        'rating' => $tour['rating']
                    );

                    array_push($result['tours'], $temp_array);
                    break;
                }
            }
            $page += 1;
            $response =  $this->get_curl_response($endpoint, $params);
            if (empty($response['data']['tours'])) {
                break;
            }
        }

        print_r($result);
        return $result;
    }




    function filter_tours_by_rating($tours_array)
    {

        print_r(' curentpage ' . $tours_array['data']['currentPage']);
        // Check if the response contains tour data
        if (isset($tours_array['data']['tours'])) {
            $tours = $tours_array['data']['tours'];
            $result = [];

            // Extract relevant data from each tour
            foreach ($tours as $tour) {
                $tour_data = [
                    'id' => $tour['id'],
                    'title' => $tour['title']['en'],
                    'description' => $tour['description']['en'],

                ];
                $result[] = $tour_data;
                print_r($tour_data);
                echo '<br>';
                echo '<br>';
            }

            print_r($result);
            return $result;
        } else {
            echo 'No tour found.';
            return [];
        }
    }


    function show_tour_events($tour_id)
    {
        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/tours/$tour_id/events";

        $response_data = $this->get_curl_response($endpoint, 0);

        if (isset($response_data['data'])) {
            $events = $response_data['data'];
            $result = [];
            foreach ($events as $event) {

                $event_data = [
                    'event_id' => $event['id'],
                    'tour_id' => $event['tourId'],
                    'language' => $event['language'],
                    'date' => $event['date']
                ];

                $result[] = $event_data;
                print_r($event_data);
                echo '<br>';
                echo '<br>';
            }


            return $result;
        } else {
            echo 'No events found.';
            return [];
        }
    }
}
