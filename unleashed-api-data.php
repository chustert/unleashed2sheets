<?php

  // configuration data
  // must use your own id and key with no extra whitespace
  $api = "https://api.unleashedsoftware.com/";
  $apiId = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
  $apiKey = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";

  // Get the request signature:
  // Based on your API id and the request portion of the url
  // - $request is only any part of the url after the "?"
  // - use $request = "" if there is no request portion
  // - for GET $request will only be the filters eg ?customerName=Bob
  // - for POST $request will usually be an empty string
  // - $request never includes the "?"
  // Using the wrong value for $request will result in an 403 forbidden response from the API
  function getSignature($request, $key) {
    return base64_encode(hash_hmac('sha256', $request, $key, true));
  }

  // Create the curl object and set the required options
  // - $api will always be https://api.unleashedsoftware.com/
  // - $endpoint must be correctly specified
  // - $requestUrl does include the "?" if any
  // Using the wrong values for $endpoint or $requestUrl will result in a failed API call
  function getCurl($id, $key, $signature, $endpoint, $requestUrl, $format) {
    global $api;

    $curl = curl_init($api . $endpoint . $requestUrl);
    curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($curl, CURLINFO_HEADER_OUT, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/$format",
          "Accept: application/$format", "api-auth-id: $id", "api-auth-signature: $signature"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    // these options allow us to read the error message sent by the API
    curl_setopt($curl, CURLOPT_FAILONERROR, false);
    curl_setopt($curl, CURLOPT_HTTP200ALIASES, range(400, 599));

    return $curl;
  }

  // GET something from the API
  // - $request is only any part of the url after the "?"
  // - use $request = "" if there is no request portion
  // - for GET $request will only be the filters eg ?customerName=Bob
  // - $request never includes the "?"
  // Format agnostic method.  Pass in the required $format of "json" or "xml"
  function get($id, $key, $endpoint, $request, $format) {
    $requestUrl = "";
    if (!empty($request)) $requestUrl = "?$request";

    try {
      // calculate API signature
      $signature = getSignature($request, $key);
      // create the curl object
      $curl = getCurl($id, $key, $signature, $endpoint, $requestUrl, $format);
      // GET something
      $curl_result = curl_exec($curl);
      error_log($curl_result);
      curl_close($curl);
      return $curl_result;
    }
    catch (Exception $e) {
      error_log('Error: ' + $e);
    }
  }

  // POST something to the API
  // - $request is only any part of the url after the "?"
  // - use $request = "" if there is no request portion
  // - for POST $request will usually be an empty string
  // - $request never includes the "?"
  // Format agnostic method.  Pass in the required $format of "json" or "xml"
  function post($id, $key, $endpoint, $format, $dataId, $data) {
    if (!isset($dataId, $data)) { return null; }

    try {
      // calculate API signature
      $signature = getSignature("", $key);
      // create the curl object.
      // - POST always requires the object's id
      $curl = getCurl($id, $key, $signature, "$endpoint/$dataId", "", $format);
      // set extra curl options required by POST
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
      // POST something
      $curl_result = curl_exec($curl);
      error_log($curl_result);
      curl_close($curl);
      return $curl_result;
    }
    catch (Exception $e) {
      error_log('Error: ' + $e);
    }
  }

  // GET in XML format
  // - gets the data from the API and converts it to an XML object
  function getXml($id, $key, $endpoint, $request) {
    // GET it
    $xml = get($id, $key, $endpoint, $request, "xml");
    // Convert to XML object and return
    return new SimpleXMLElement($xml);
  }

  // POST in XML format
  // - the object to POST must be a valid XML object. Not stdClass, not array, not associative.
  // - converts the object to string and POSTs it to the API
  function postXml($id, $key, $endpoint, $dataId, $data) {

    $xml = $data->asXML();

    // must remove the <xml version="1.0"> node if present, the API does not want it
    $pos = strpos($xml, '<?xml version="1.0"?>');
    if ($pos !== false) {
      $xml = str_replace('<?xml version="1.0"?>', '', $xml);
    }

    // if the data does not have the correct xml namespace (xmlns) then add it
    $pos1 = strpos($xml, 'xmlns="http://api.unleashedsoftware.com/version/1"');
    if ($pos1 === false) {
      // there should be a better way than this
      // using preg_replace with count = 1 will only replace the first occurance
      $xml = preg_replace(' />/i',' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns="http://api.unleashedsoftware.com/version/1">',$xml,1);
    }

    // POST it
    $posted = post($id, $key, $endpoint, "xml", $dataId, $xml );
    // Convert to XML object and return
    // - the API always returns the POSTed object back as confirmation
    return new SimpleXMLElement($posted);
  }

  // GET in JSON format
  // - gets the data from the API and converts it to an stdClass object
  function getJson($id, $key, $endpoint, $request) {
    // GET it, decode it, return it
    return json_decode(get($id, $key, $endpoint, $request, "json"), true);
  }

  // POST in JSON format
  // - the object to POST must be a valid stdClass object. Not array, not associative.
  // - converts the object to string and POSTs it to the API
  function postJson($id, $key, $endpoint, $dataId, $data) {
    // POST it, return the API's response
    return post($id, $key, $endpoint, "json", $dataId, json_encode($data));
  }



// *******************************************************************************

  function getAssemblies($startDate, $endDate, $assemblyStatus, $format) {
    global $apiId, $apiKey;

    if ($format == "xml")
      return getXml($apiId, $apiKey, "Assemblies", "startDate=$startDate&endDate=$endDate&assemblyStatus=$assemblyStatus");
    else
      return getJson($apiId, $apiKey, "Assemblies", "startDate=$startDate&endDate=$endDate&assemblyStatus=$assemblyStatus");
  }

  function getSOH($warehouseCode, $format) {
    global $apiId, $apiKey;

    if ($format == "xml")
      return getXml($apiId, $apiKey, "StockOnHand", "warehouseCode=$warehouseCode");
    else
      return getJson($apiId, $apiKey, "StockOnHand", "warehouseCode=$warehouseCode");
  }

  function getAllProducts($format) {
    global $apiId, $apiKey;

    if ($format == "xml")
      return getXml($apiId, $apiKey, "Products", "");
    else
      return getJson($apiId, $apiKey, "Products", "");
  }

// *******************************************************************************

  function getProducts() {
    echo "Starting Products: getAllProducts" . "<br />";
    echo "<br />";
    echo "<br />";

    $json_array = getAllProducts("json");

    foreach ($json_array['Items'] as $value) {
      if (isset($value['ProductGroup']['GroupName'])) {
        if (
          $value['ProductGroup']['GroupName'] == "Additives" || 
          $value['ProductGroup']['GroupName'] == "Cartons" || 
          $value['ProductGroup']['GroupName'] == "Coconut Cream" ||
          $value['ProductGroup']['GroupName'] == "Cultures" ||
          $value['ProductGroup']['GroupName'] == "Fruit Preps & Flavours" ||
          $value['ProductGroup']['GroupName'] == "Ingredients" ||
          $value['ProductGroup']['GroupName'] == "Jars & Lids" ||
          $value['ProductGroup']['GroupName'] == "Labels" ||
          $value['ProductGroup']['GroupName'] == "Thickeners") 
        {
          echo $value['ProductCode']." ";
          echo $value['ProductDescription']." ";
          echo (isset($value['UnitOfMeasure']['Name'])) ? $value['UnitOfMeasure']['Name']." " : "n/A ";
          echo (isset($value['DefaultPurchasePrice'])) ? $value['DefaultPurchasePrice']." " : "n/A ";
          echo (isset($value['ProductGroup']['GroupName'])) ? $value['ProductGroup']['GroupName']." " : "n/A ";
          echo (isset($value['Supplier']['SupplierName'])) ? $value['Supplier']['SupplierName']." " : "n/A ";
          echo (isset($value['Supplier']['SupplierCode'])) ? $value['Supplier']['SupplierCode']." " : "n/A ";
          echo (isset($value['Supplier']['SupplierProductCode'])) ? $value['Supplier']['SupplierProductCode']." " : "n/A ";
          echo (isset($value['Supplier']['SupplierProductDescription'])) ? $value['Supplier']['SupplierProductDescription']." " : "n/A ";
          echo (isset($value['Supplier']['SupplierProductPrice'])) ? $value['Supplier']['SupplierProductPrice']."<br />" : "n/A<br />";
        }
      }
    }    

    echo "<br />";
    echo "<br />";
    echo "-------------------------------------------------------------------------------------<br />";

    require __DIR__ . '/vendor/autoload.php';
    /*
     * We need to get a Google_Client object first to handle auth and api calls, etc.
     */
    $client = new \Google_Client();
    $client->setApplicationName('Unleashed2Sheets');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/credentials.json');

    /*
     * With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
     */
    $service = new \Google_Service_Sheets($client);

    /*
     * To read data from a sheet we need the spreadsheet ID and the range of data we want to retrieve.
     * Range is defined using A1 notation, see https://developers.google.com/sheets/api/guides/concepts#a1_notation
     */
    $spreadsheetId = '16xgDAWRZoSXbLxoQXU2vWy004A_1tNYZR1r4W2FgPHw';
    $range = 'Unleashed Import Products!A3:J';


    // CLEAR OPERATION  ***************************************************
    $requestBody = new Google_Service_Sheets_ClearValuesRequest();
    $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);
    // CLEAR OPERATION END ***************************************************

    foreach ($json_array['Items'] as $value) {
      if (isset($value['ProductGroup']['GroupName'])) {
        if (
          $value['ProductGroup']['GroupName'] == "Additives" || 
          $value['ProductGroup']['GroupName'] == "Cartons" || 
          $value['ProductGroup']['GroupName'] == "Coconut Cream" ||
          $value['ProductGroup']['GroupName'] == "Cultures" ||
          $value['ProductGroup']['GroupName'] == "Fruit Preps & Flavours" ||
          $value['ProductGroup']['GroupName'] == "Ingredients" ||
          $value['ProductGroup']['GroupName'] == "Jars & Lids" ||
          $value['ProductGroup']['GroupName'] == "Labels" ||
          $value['ProductGroup']['GroupName'] == "Thickeners") 
        {
          $values[] = [
                $value['ProductCode'],
                $value['ProductDescription'],
                (isset($value['UnitOfMeasure']['Name'])) ? $value['UnitOfMeasure']['Name'] : "n/A",
                (isset($value['DefaultPurchasePrice'])) ? $value['DefaultPurchasePrice'] : "n/A",
                (isset($value['ProductGroup']['GroupName'])) ? $value['ProductGroup']['GroupName'] : "n/A",
                (isset($value['Supplier']['SupplierName'])) ? $value['Supplier']['SupplierName'] : "n/A",
                (isset($value['Supplier']['SupplierCode'])) ? $value['Supplier']['SupplierCode'] : "n/A",
                (isset($value['Supplier']['SupplierProductCode'])) ? $value['Supplier']['SupplierProductCode'] : "n/A",
                (isset($value['Supplier']['SupplierProductDescription'])) ? $value['Supplier']['SupplierProductDescription'] : "n/A",
                (isset($value['Supplier']['SupplierProductPrice'])) ? $value['Supplier']['SupplierProductPrice'] : "n/A",
              ];
        }
      }
    }


    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
        ]);
    $params = [
        'valueInputOption' => 'RAW'
        ];
    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        $params
    );

  }

  // Call the GET SOH methon
  function getSOHRaglan() {
    echo "Starting SOH: getSOHRaglan" . "<br />";

    $warehouseCode = "HQ";
    
    $json_array_SOH = getSOH($warehouseCode, "json");

    echo "<br />";
    echo "<br />";

    foreach($json_array_SOH['Items'] as $items) {
      echo $items['ProductCode']." ";
      echo $items['ProductGroupName']." ";
      echo $items['ProductDescription']." ";
      echo $items['Warehouse']." ";
      echo $items['OnPurchase']." ";
      echo $items['QtyOnHand']." ";
      echo $items['AvailableQty']."<br />";
    }

    echo "<br />";
    echo "<br />";
    echo "-------------------------------------------------------------------------------------<br />";


    require __DIR__ . '/vendor/autoload.php';
    /*
     * We need to get a Google_Client object first to handle auth and api calls, etc.
     */
    $client = new \Google_Client();
    $client->setApplicationName('Unleashed2Sheets');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/credentials.json');

    /*
     * With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
     */
    $service = new \Google_Service_Sheets($client);

    /*
     * To read data from a sheet we need the spreadsheet ID and the range of data we want to retrieve.
     * Range is defined using A1 notation, see https://developers.google.com/sheets/api/guides/concepts#a1_notation
     */
    $spreadsheetId = '16xgDAWRZoSXbLxoQXU2vWy004A_1tNYZR1r4W2FgPHw';
    $range = 'Unleashed Import SOH!A3:G';


    // CLEAR OPERATION  ***************************************************
    $requestBody = new Google_Service_Sheets_ClearValuesRequest();
    $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);
    // CLEAR OPERATION END ***************************************************


    foreach($json_array_SOH['Items'] as $items) {
        $values[] = [
                $items['ProductCode'],
                $items['ProductGroupName'],
                $items['ProductDescription'],
                $items['Warehouse'],
                $items['OnPurchase'],
                $items['QtyOnHand'],
                $items['AvailableQty'],
            ];
    }

    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
        ]);
    $params = [
        'valueInputOption' => 'RAW'
        ];
    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        $params
    );
  }




  function getSOHMainfreight() {
    echo "Starting SOH: getSOHMainfreight" . "<br />";

    $warehouseCode = "MFKM";
    
    $json_array_SOH = getSOH($warehouseCode, "json");

    echo "<br />";
    echo "<br />";

    foreach($json_array_SOH['Items'] as $items) {
      echo $items['ProductCode']." ";
      echo $items['ProductGroupName']." ";
      echo $items['ProductDescription']." ";
      echo $items['Warehouse']." ";
      echo $items['OnPurchase']." ";
      echo $items['QtyOnHand']." ";
      echo $items['AvailableQty']."<br />";
    }

    echo "<br />";
    echo "<br />";
    echo "-------------------------------------------------------------------------------------<br />";


    require __DIR__ . '/vendor/autoload.php';
    /*
     * We need to get a Google_Client object first to handle auth and api calls, etc.
     */
    $client = new \Google_Client();
    $client->setApplicationName('Unleashed2Sheets');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/credentials.json');

    /*
     * With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
     */
    $service = new \Google_Service_Sheets($client);

    /*
     * To read data from a sheet we need the spreadsheet ID and the range of data we want to retrieve.
     * Range is defined using A1 notation, see https://developers.google.com/sheets/api/guides/concepts#a1_notation
     */
    $spreadsheetId = '16xgDAWRZoSXbLxoQXU2vWy004A_1tNYZR1r4W2FgPHw';
    $range = 'Unleashed Import SOH!H3:N';


    // CLEAR OPERATION  ***************************************************

    $requestBody = new Google_Service_Sheets_ClearValuesRequest();
    $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

    // CLEAR OPERATION END ***************************************************

    foreach($json_array_SOH['Items'] as $items) {
        $values[] = [
                $items['ProductCode'],
                $items['ProductGroupName'],
                $items['ProductDescription'],
                $items['Warehouse'],
                $items['OnPurchase'],
                $items['QtyOnHand'],
                $items['AvailableQty'],
            ];
    }

    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
        ]);
    $params = [
        'valueInputOption' => 'RAW'
        ];
    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        $params
    );
  }





  // Call the GET products method and print the results
  function getAssembliesAvgFourWeeks() {
    echo "Starting: getAssembliesAvgFourWeeks" . "<br />";
    
    $startDate  = strtotime("Sunday 4 week ago");
    $endDate    = strtotime("last saturday");

    $startDate = date("Y-m-d", $startDate);
    $endDate = date("Y-m-d", $endDate);

    $assemblyStatus = "Completed";

    echo "-------------------------------------------------------<br />";
    echo "GET assemblies in JSON format:" . "<br />";
    echo "<br />";
    echo "From: " . $startDate . "<br />";
    echo "To: " . $endDate;


    $json_array = getAssemblies($startDate, $endDate, $assemblyStatus, "json");


    echo "<br />";
    echo "<br />";

    foreach($json_array['Items'] as $value) {
      foreach($value['AssemblyLines'] as $lines) {
        echo $value['AssemblyNumber']." ";
        echo $lines['Product']['ProductCode']." ";
        echo $lines['Product']['ProductDescription']." ";
        echo $lines['Quantity']." ";
        echo "<br />";
        echo "<br />";
      }
    }    


    echo "<br />";
    echo "<br />";
    echo "-------------------------------------------------------------------------------------<br />";


    require __DIR__ . '/vendor/autoload.php';
    /*
     * We need to get a Google_Client object first to handle auth and api calls, etc.
     */
    $client = new \Google_Client();
    $client->setApplicationName('Unleashed2Sheets');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/credentials.json');

    /*
     * With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
     */
    $service = new \Google_Service_Sheets($client);

    /*
     * To read data from a sheet we need the spreadsheet ID and the range of data we want to retrieve.
     * Range is defined using A1 notation, see https://developers.google.com/sheets/api/guides/concepts#a1_notation
     */
    $spreadsheetId = '16xgDAWRZoSXbLxoQXU2vWy004A_1tNYZR1r4W2FgPHw';
    $range = 'Unleashed Assemblies Import!A3:D';


    // CLEAR OPERATION  ***************************************************

    $requestBody = new Google_Service_Sheets_ClearValuesRequest();
    $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

    // CLEAR OPERATION END ***************************************************


    foreach($json_array['Items'] as $value) {
      foreach($value['AssemblyLines'] as $lines) {
        $values[] = [
                $value['AssemblyNumber'], 
                $lines['Product']['ProductCode'], 
                $lines['Product']['ProductDescription'], 
                $lines['Quantity'],
            ];
      }
    }

    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
        ]);
    $params = [
        'valueInputOption' => 'RAW'
        ];
    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        $params
    );

  }





  function getAssembliesAvgTwelveWeeks() {
    echo "Starting: getAssembliesAvgTwelveWeeks" . "<br />";
    
    $startDate  = strtotime("Sunday 12 week ago");
    $endDate    = strtotime("last saturday");

    $startDate = date("Y-m-d", $startDate);
    $endDate = date("Y-m-d", $endDate);

    $assemblyStatus = "Completed";

    echo "-------------------------------------------------------<br />";
    echo "GET assemblies in JSON format:" . "<br />";
    echo "<br />";
    echo "From: " . $startDate . "<br />";
    echo "To: " . $endDate;
 
    $json_array_TWELVE = getAssemblies($startDate, $endDate, $assemblyStatus, "json");

    echo "<br />";
    echo "<br />";

    foreach($json_array_TWELVE['Items'] as $items) {
      foreach($json_array_TWELVE['Items'][0]['AssemblyLines'] as $assmeblyLines) {
        echo $items['AssemblyNumber']." ";
        echo $assmeblyLines['Product']['ProductCode']." ";
        echo $assmeblyLines['Product']['ProductDescription']." ";
        echo $assmeblyLines['Quantity']."<br />";
      }
    }


    echo "<br />";
    echo "<br />";
    echo "-------------------------------------------------------------------------------------<br />";


    require __DIR__ . '/vendor/autoload.php';
    /*
     * We need to get a Google_Client object first to handle auth and api calls, etc.
     */
    $client = new \Google_Client();
    $client->setApplicationName('Unleashed2Sheets');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');

    $client->setAuthConfig(__DIR__ . '/credentials.json');

    /*
     * With the Google_Client we can get a Google_Service_Sheets service object to interact with sheets
     */
    $service = new \Google_Service_Sheets($client);

    /*
     * To read data from a sheet we need the spreadsheet ID and the range of data we want to retrieve.
     * Range is defined using A1 notation, see https://developers.google.com/sheets/api/guides/concepts#a1_notation
     */
    $spreadsheetId = '16xgDAWRZoSXbLxoQXU2vWy004A_1tNYZR1r4W2FgPHw';
    $range = 'Unleashed Assemblies Import!E3:H';


    // CLEAR OPERATION  ***************************************************

    $requestBody = new Google_Service_Sheets_ClearValuesRequest();
    $response = $service->spreadsheets_values->clear($spreadsheetId, $range, $requestBody);

    // CLEAR OPERATION END ***************************************************


    foreach($json_array_TWELVE['Items'] as $items) {
      foreach($json_array_TWELVE['Items'][0]['AssemblyLines'] as $assmeblyLines) {
        $values[] = [
                $items['AssemblyNumber'], 
                $assmeblyLines['Product']['ProductCode'],
                $assmeblyLines['Product']['ProductDescription'],
                $assmeblyLines['Quantity'],
            ];
      }
    }

    $body = new Google_Service_Sheets_ValueRange([
        'values' => $values
        ]);
    $params = [
        'valueInputOption' => 'RAW'
        ];
    $result = $service->spreadsheets_values->update(
        $spreadsheetId,
        $range,
        $body,
        $params
    );

  }

// ************************************************************************

  getProducts();
  getSOHRaglan();
  getSOHMainfreight();
  getAssembliesAvgFourWeeks();
  // getAssembliesAvgTwelveWeeks();

?>