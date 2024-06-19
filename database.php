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

    function getAccessTokenFromURL($url)
    {
        $data = file_get_contents($url);
        // Parsing the token from the response. Adjust according to the actual response structure.
        $str = substr($data, strpos($data, "accessToken") + 35, 344);
        return $str;
    }

    function get_free_tour_countries($accessToken)
    {
        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/countries";
        $array = [];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: Bearer ' . $accessToken,
                'Accept: application/json'
            )
        );

        $response = curl_exec($ch);
        if ($response === false) {
            echo "cURL Error: " . curl_error($ch);
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['data'])) {
                foreach ($responseData['data'] as $country) {
                    $array[] = [
                        'id' => $country['id'],
                        'name' => $country['title']['en']
                    ];
                }
                return $array;
            } else {
                echo "No country data found in the response.";
            }
        }
        curl_close($ch);
        return [];
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


    function create_country_table()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        try {
            $sql = "CREATE TABLE countries (
                        id INT(6) UNIQUE NOT NULL,
                        country_name VARCHAR(50) UNIQUE NOT NULL
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


    function insert_countries_into_db()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die("Connection error: " . $conn->connect_error);
        }
        echo "Connected successfully";

        $countriesArray = $this->get_free_tour_countries($this->accessToken);
        $stmt = $conn->prepare("INSERT INTO countries (id, country_name) VALUES (?, ?)");
        $stmt->bind_param("is", $id, $name);

        try {
            foreach ($countriesArray as $country) {
                $id = $country['id'];
                $name = $country['name'];
                $stmt->execute();
                echo 'ID and Name added successfully';
            }
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        } finally {
            $stmt->close();
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
        $url = $endpoint . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Authorization: Bearer ' . $this->accessToken,
                'Accept: application/json'
            )
        );

        $response = curl_exec($ch);
        if ($response === false) {
            echo "cURL Error: " . curl_error($ch);
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['data'])) {
                return $responseData['data'];
            } else {
                echo "No city data found in the response.";
            }
        }
        curl_close($ch);
        return [];
    }

    function insert_cities_into_db()
    {
        $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbName);
        if ($conn->connect_error) {
            die("Connection error: " . $conn->connect_error);
        }
        echo "Connected successfully";

        $countriesArray = $this->get_free_tour_countries($this->accessToken);

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
        // get tour list from ids

        // get a list of tours in that city

        // show them


    }

    function get_tours_by_names($country_name, $city_name, $ndays)
    {
        // Get the IDs of the country and city by their names
        $ids = $this->get_country_and_city_id_by_names($country_name, $city_name);

        // If IDs are not found, return an empty array
        if (!$ids) {
            return [];
        }

        $country_id = $ids['country_id'];
        $city_id = $ids['city_id'];

        // Define the endpoint URL and parameters
        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/tours";
        $params = array(
            'cityId' => $city_id,
            'countryId' => $country_id,
            'onlyFree' => 0,
            'onlyPaid' => 0,
            'days' => $ndays
        );
        $url = $endpoint . '?' . http_build_query($params);

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

        // Check for cURL errors
        if ($response === false) {
            echo "cURL error: " . curl_error($ch);
            curl_close($ch);
            return [];
        } else {
            // Decode the JSON response
            $response_data = json_decode($response, true);
            curl_close($ch);

            // Check if the response contains tour data
            if (isset($response_data['data']['tours'])) {
                $tours = $response_data['data']['tours'];
                $result = [];

                // Extract relevant data from each tour
                foreach ($tours as $tour) {
                    $tour_data = [
                        'id' => $tour['id'],
                        'title' => $tour['title']['en'],
                        'description' => $tour['description']['en'],
                    ];
                    $result[] = $tour_data;
                }
                return $result;
            } else {
                echo 'No tour found.';
                return [];
            }
        }
    }

    function show_tour_events($tour_id)
    {


        $endpoint = "https://www.freetour.com/partnersAPI/v.2.0/tours/$tour_id/events";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json'
        ));

        $response = curl_exec($ch);


        if ($response === false) {
            echo 'cURL Error: ' . curl_error($ch);
            curl_close($ch);
            return [];
        } else {

            $response_data = json_decode($response, true);
            curl_close($ch);
        }

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
                print_r($result);
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
