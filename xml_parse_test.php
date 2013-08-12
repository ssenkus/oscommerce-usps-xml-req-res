<?php
/*
  $Id: xml_parse_test.php 20130802 Kymation $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2013 osCommerce

  Released under the GNU General Public License
*/


/* 
 * Test to determine the array structure of a USPS XML response 
 *   and parse that structure into arrays of services and special/extra services.
 * This data will be used to install the module.
 * */


  /////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
  // To use this program:
  //   Replace the XXXXXX in Line 29 with a valid USPS username/user id
  //     then upload this file to the root of an osCommerce store
  ////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
 

  // Define the constants that would normally already exist or be defined at installation
  define('USPS_USERNAME', '772xxxxx1819'); // <-- Replace the XXXXXX ONLY with a valid ID <--
  define('SHIPPING_ORIGIN_ZIP', 90012); // From Los Angeles
  define('SHIPPING_DOMESTIC_DEST_ZIP', 10004); // To New York
  define('SHIPPING_INTERNATIONAL_DEST_COUNTRY', 'Germany'); // Or to Germany
  define('INSTALL_SHIP_DATE', date('d-M-Y', strtotime('+1 day'))); //Needs to be within the next three days, so use tomorrow


  // Include the osC files we need
  require_once 'includes/functions/general.php';
  require_once 'includes/classes/http_client.php';


  ////
  // Domestic request and response
  // First build the request
  $prelim_request = install_domestic_prelim( $ship_date );
  // Send the request to USPS and get the XML array of services
  $prelim_response = retrieve_usps_response( $prelim_request );
  // Convert the XML to a usable array
  $prelim_array = extract_domestic_classid( $prelim_response );
  // Sort the result by name, since the default order is horrible
  array_sort_by_column( $prelim_array, 'text' );
  
  /*
  print '$prelim_request: <pre>';
  print_r( $prelim_response );
  print '</pre>';  

  
  print '$prelim_response: <pre>';
  print_r( $prelim_response );
  print '</pre>';  
  */
  
  
  echo '<!DOCTYPE html>';
  echo '<html>';
  echo '<head>';
  echo '<title>Test Request/Response from USPS Servers</title>';
  echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
  echo '<link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css" rel="stylesheet">';
  echo '<script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>';
  echo '</head>';
  echo '<body>';
  // Let's see the result
  echo '<h1 style="margin: 0 auto 10px; width: 400px;">Domestic Services:</h1>';
  $i = 0;
  echo '<table style="width:800px; margin: 20px auto;" class="table table-condensed table-hover table-striped table-bordered" >';
  echo '	<tr class="success">';
  echo '		<th>Index</th>';
  echo '		<th>ID</th>';
  echo '		<th>Service</th>';
  echo '		<th>Rate</th>';  
  echo '	</tr>';
  
  foreach($prelim_array as $service) {
	  $tr_class = ($i % 2 == 0) ? 'success': 'info';
	  echo "<tr class=\"$tr_class\">";
	  echo "    <td>".$i++."</td><td>".$service['id']."</td><td>".$service['text']."</td><td>".$service['rate']."</td>";
	  echo '</tr>';
  }
  
  echo '</table>';

  echo '</body>';
  echo '</html>';
  // Adding all services to a single request fails with an incomprehensible error message
  //   but sending the exact same requests one at a time works. 
  // There may be a bug in the Request XML or it may be in the USPS server.
  // This is probably not worth fixing, even if it's possible.
  // Step through the service types and send a full request for each one,
  //   then parse the response for the Special Service name and ID
  $special_services = array();
  foreach( $prelim_array as $single_service ) {
    // Put the array back together
    $single_array = array( $single_service );
    // Build the request for the service class specified
    $final_request = install_domestic_final( $single_array );
    // Send the request
    $final_response = retrieve_usps_response( $final_request );
    // Get the special services from the XML
    extract_domestic_serviceid( $final_response, $special_services ); // Services are loaded into the $special_services array
  }
  // Sort the services by ID. Just because.
  array_sort_by_column( $special_services, 'text' );
  // Let's see the result
  print 'Domestic Special Services: <pre>';
  $i = 0;
  foreach($special_services as $service) {
		echo $i++ . ' - ID: ' . $service['id'] . ' Service: '. $service['text'] . '<br />';
  }
  
  print '</pre>';


  ////
  // International request and response
  // Start with the request XML
  $intl_request = install_international();  
  // Send the request to USPS and get the XML array of services
  $intl_response = retrieve_usps_response( $intl_request );
  // Convert the XML to a usable array
  $intl_array = extract_international_classid( $intl_response );
  // Sort the result by name, since the default order is horrible
  array_sort_by_column( $intl_array, 'text' );
  // Let's see the result
  print 'International Services: <pre>';
  print_r( $intl_array );
  print '</pre>';

  // Get the special services from the XML
  $special_services_intl = array();
  extract_international_serviceid( $intl_response, $special_services_intl ); // Services are loaded into the $special_services_intl array
  // Sort the services by name. Just because.
  array_sort_by_column( $special_services_intl, 'text' );
  // Let's see the result
  print 'International Extra Services: <pre>';
  print_r( $special_services_intl );
  print '</pre>';
  


  /////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\
  // Functions
  // Convert these to class methods for the real module
  ////////////////////////////////////\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\\

  ////
  // Build the request for the preliminary install data
  function install_domestic_prelim() {
    $request = '
            <RateV4Request USERID="' . USPS_USERNAME . '">
              <Revision>2</Revision>
              <Package ID="1">
              <Service>ALL</Service>
              <ZipOrigination>' . SHIPPING_ORIGIN_ZIP . '</ZipOrigination>
              <ZipDestination>' . SHIPPING_DOMESTIC_DEST_ZIP . '</ZipDestination>
              <Pounds>1</Pounds>
              <Ounces>1</Ounces>
              <Container/>
              <Size>REGULAR</Size>
              <Machinable>true</Machinable>
              <ShipDate>' . INSTALL_SHIP_DATE . '</ShipDate>
              </Package>
            </RateV4Request>
          ';
    $request = 'API=RateV4&XML=' . urlencode($request);

    return $request;
  }
  

  ////
  // Build the final request for the domestic install
  function install_domestic_final( $services_array ) {
    $request = '<RateV4Request USERID="' . USPS_USERNAME . '">';
    $request .= '<Revision>2</Revision>';
    
    $package_count = 0;
    foreach( $services_array as $service_data ) {
      $container = '';
      $size = 'Regular';
      $FirstClassMailType = '';
      // Fix First Class
      if( $service_data['id'] > 99 ) $service_data['id'] = 0;

      switch( $service_data['id'] ) {
        case 0 : //First Class
          $service = 'First Class Commercial';
          $first_class_type = $service_data['text'];
          $first_class_type = substr( $first_class_type, 0, strpos( $first_class_type, '&' ) ); // everything before the trademark
          $first_class_type = strip_tags( $first_class_type ); // Remove the superscript tags
          $first_class_type = preg_replace( '/&#?[a-z0-9]{2,8};/i', '', $first_class_type ); // Remove all HTML entities
          $first_class_type = strtolower( trim( $first_class_type ) );

          switch( $first_class_type ) {
            case 'letter' :
              $FirstClassMailType = 'LETTER';
              break;

            case 'large envelope' :
              $FirstClassMailType = 'FLAT';
              break;

            case 'postcards' :
              $FirstClassMailType = 'POSTCARD';
              break;

            case 'letter' :
            default;
              $FirstClassMailType = 'PARCEL';
              break;
          } //switch( $first_class_type
          break;

        case 1 : //Priority Mail
          $service = 'Priority Commercial';
          $size = 'Regular';
          break;

        case 2 : //Priority Mail Express  Hold For Pickup
          $service = 'Express Commercial';
          break;

        case 3 : //Priority Mail Express
          $service = 'Express Commercial';
          break;

        case 4 : //Standard Post
          $service = 'Standard Post';
          $size = 'Regular';
          break;

        case 6 : //Media Mail
          $service = 'Media Mail';
          $size = 'Regular';
          break;

        case 7 : // Library Mail
          $service = 'Library Mail';
          $container = 'Variable';
          $size = 'Regular';
          break;

        case 13 : //Priority Mail Express  Flat Rate Envelope
          $service = 'Express Commercial';
          $container = 'FLAT RATE ENVELOPE';
          break;

        case 15 : // First-Class Mail Large Postcards -- always returns an error.
          $service = 'First Class';
          $FirstClassMailType = 'POSTCARD';
          $size = 'Large';
          break;

        case 16 : //Priority Mail Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'FLAT RATE ENVELOPE';
          $size = 'Regular';
          break;

        case 17 : //Priority Mail Medium Flat Rate Box
          $service = 'Priority Commercial';
          $container = 'MD FLAT RATE BOX';
          $size = 'Regular';
          break;

        case 22 : //Priority Mail Large Flat Rate Box
          $service = 'Priority Commercial';
          $container = 'LG FLAT RATE BOX';
          $size = 'Regular';
          break;

        case 27 : //Priority Mail Express  Flat Rate Envelope Hold For Pickup
          $service = 'Express Commercial';
          $container = 'FLAT RATE ENVELOPE';
          break;

        case 28 : //Priority Mail Small Flat Rate Box
          $service = 'Priority Commercial';
          $container = 'SM FLAT RATE BOX';
          break;

        case 29 : //Priority Mail Padded Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'PADDED FLAT RATE ENVELOPE';
          $size = 'Regular';
          break;

        case 30 : //Priority Mail Express  Legal Flat Rate Envelope
          $service = 'Express Commercial';
          $container = 'LEGAL FLAT RATE ENVELOPE';
          break;

        case 31 : //Priority Mail Express  Legal Flat Rate Envelope Hold For Pickup
          $service = 'Express Commercial';
          $container = 'LEGAL FLAT RATE ENVELOPE';
          break;

        case 38 : //Priority Mail Gift Card Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'GIFT CARD FLAT RATE ENVELOPE';
          break;

        case 40 : //Priority Mail Window Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'WINDOW FLAT RATE ENVELOPE';
          break;

        case 42 : //Priority Mail Small Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'SM FLAT RATE ENVELOPE';
          break;

        case 44 : //Priority Mail Legal Flat Rate Envelope
          $service = 'Priority Commercial';
          $container = 'LEGAL FLAT RATE ENVELOPE';
          break;

        case 55 : //Priority Mail Express  Flat Rate Boxes
          $service = 'Express Commercial';
          $container = 'FLAT RATE BOX';
          break;

        case 56 : //Priority Mail Express  Flat Rate Boxes Hold For Pickup
          $service = 'Express Commercial';
          $container = 'FLAT RATE BOX';
          break;

        case 62 : //Priority Mail Express  Padded Flat Rate Envelope
          $service = 'Express Commercial';
          $container = 'PADDED FLAT RATE ENVELOPE';
          break;

        case 63 : //Priority Mail Express  Padded Flat Rate Envelope Hold For Pickup
          $service = 'Express Commercial';
          $container = 'PADDED FLAT RATE ENVELOPE';
          break;

        case 61 : // Bogus service returning First Class?
        default : // Return nothing if something went wrong
          $service = ''; // The check for Service will block the request for these
          break;
      } //switch( $service_data['id']

      if( tep_not_null( $service ) ) {  // One blank Service will cause the whole request to error out
        $request .= 
        '<Package ID="' . $package_count . '">' .
        '<Service>' . $service . '</Service>' .
         ($FirstClassMailType != '' ? '<FirstClassMailType>' . $FirstClassMailType . '</FirstClassMailType>' : '') .
        '<ZipOrigination>' . SHIPPING_ORIGIN_ZIP . '</ZipOrigination>' .
        '<ZipDestination>' . SHIPPING_DOMESTIC_DEST_ZIP . '</ZipDestination>' .
        '<Pounds>0</Pounds>' .
        '<Ounces>2</Ounces>' .
        ( tep_not_null( $container ) ? '<Container>' . $container . '</Container>' : '<Container />' ) .
        '<Size>' . $size . '</Size>' .
        '<Machinable>true</Machinable>' .
        '</Package>';
      }
      $package_count++;
    }

    $request .= '</RateV4Request>';
    $request = 'API=RateV4&XML=' . urlencode( $request );
    return $request;
  } // function install_domestic_final
  

  ////
  // Build the request for the international install
  function install_international() {  
    $request = '<IntlRateV2Request USERID="' . USPS_USERNAME . '">';
    $request .= '<Revision>2</Revision>' . 
        '<Package ID="0">' .
        '<Pounds>0</Pounds>' .
        '<Ounces>2</Ounces>' .
        '<MailType>All</MailType>' .
        '<GXG>' .
        '<POBoxFlag>N</POBoxFlag>' .
        '<GiftFlag>N</GiftFlag>' .
        '</GXG>' .
        '<ValueOfContents>10</ValueOfContents>' .
        '<Country>' . SHIPPING_INTERNATIONAL_DEST_COUNTRY . '</Country>' .
        '<Container>RECTANGULAR</Container>' .
        '<Size>LARGE</Size>' .
        '<Width>2</Width>' .
        '<Length>10</Length>' .
        '<Height>6</Height>' .
        '<Girth>0</Girth>' .
        '<OriginZip>' . SHIPPING_ORIGIN_ZIP . '</OriginZip>' .
        '<CommercialFlag>N</CommercialFlag>' .
        '<ExtraServices>' .
        '<ExtraService>0</ExtraService>' .
        '<ExtraService>1</ExtraService>' .
        '<ExtraService>2</ExtraService>' .
        '<ExtraService>3</ExtraService>' .
        '<ExtraService>5</ExtraService>' .
        '<ExtraService>6</ExtraService>' .
        '</ExtraServices>' .
        '</Package>' .
        '</IntlRateV2Request>';
        
    $request = 'API=IntlRateV2&XML=' . urlencode( $request );
    return $request;
  }
  

  ////
  // Connect to the USPS server, send a request, and retrieve a response in array form
  function retrieve_usps_response( $request ) {
    $http = new httpClient();

    if ($http->Connect('production.shippingapis.com', 80)) {
      $http->addHeader('Host', 'production.shippingapis.com');
      $http->addHeader('User-Agent', 'osCommerce');
      $http->addHeader('Connection', 'Close');
      if ($http->Get('/shippingapi.dll?' . $request)) {
        $response = $http->getBody();
      }
      $http->Disconnect();
    }
    $response_array = xml_to_array( $response );

    return $response_array;
  }
  

  ////
  // Convert XML to an array
  function xml_to_array( $xmlstring ) {
    $xml = simplexml_load_string( $xmlstring );
    $json = json_encode( $xml );
    $array = json_decode( $json, TRUE );

    return $array;
  } //function xml_to_array
  
  
  ////
  // Sort a multi-dimensional array by a second-level value
  // Function courtesy Tom Haigh, with thanks
  function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
    $sort_col = array();
    foreach ($arr as $key=> $row) {
      $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
  }


  ////
  // Extract CLASSID and MailService pairs from the USPS response array
  // This also involves modifying the CLASSID for First Class, since they are all 0
  function extract_domestic_classid( $response ) {
    if (is_array($response) && is_array($response['Package'])) {
      $first_class_modifier = 100;
      $return_array = array ();
      
      if( isset( $response['Package']['Postage'] ) && is_array( $response['Package']['Postage'] ) ) {
        foreach ($response['Package']['Postage'] as $service) {
          // Handle First Class as a special case
          if (is_array($service['@attributes']) && isset ($service['@attributes']['CLASSID']) && $service['@attributes']['CLASSID'] == 0) {
            $service['@attributes']['CLASSID'] = $service['@attributes']['CLASSID'] + $first_class_modifier;
            // $service['MailService'] = $service['Postage']['MailService'] . ' ' . ucwords( strtolower( $service['FirstClassMailType'] ) );
          }
          $first_class_modifier++;

          // Extract the CLASSID and MailService values and add them to the output
          $return_array[] = array (
            'id' => $service['@attributes']['CLASSID'],
            'text' => $service['MailService'],
			'rate' => $service['Rate']
          );
        }
        return $return_array;
      }
    } else {
      return false;
    }
  } // function extract_classid


  ////
  // Extract CLASSID and MailService pairs from the USPS response array
  // This also involves modifying the CLASSID for First Class, since they are all 0
  function extract_international_classid( $response ) {
    if( is_array( $response ) && is_array( $response['Package'] ) ) {
      $return_array = array ();
      
      if( isset( $response['Package']['Service'] ) && is_array( $response['Package']['Service'] ) ) {
        foreach ($response['Package']['Service'] as $service) {
          // Extract the CLASSID and MailService values and add them to the output
          $return_array[] = array (
            'id' => $service['@attributes']['ID'],
            'text' => $service['SvcDescription']
          );
        }
        return $return_array;
      }
    } else {
      return false;
    }
  } // function extract_classid


  ////
  // Extract Special Service name and ID pairs from the domestic response array
  //   Duplicates are removed, and there is no relation to which services are permitted
  function extract_domestic_serviceid( $response, &$return_array ) {
    if( is_array( $response ) && is_array( $response['Package']['Postage']['SpecialServices']['SpecialService'] ) ) {
      
      foreach ($response['Package']['Postage']['SpecialServices']['SpecialService'] as $special_service) {
        // check to see if we already have this service
        $already_in_array = false;
        if( count( $return_array ) > 0 ) {
          foreach( $return_array as $existing_value ) {
            if( $special_service['ServiceID'] == $existing_value['id'] ) {
              $already_in_array = true;
              break 2;
            }
          }
        }

        if( $already_in_array == false ) {
          // Extract the ServiceID and ServiceName values and add them to the output
          $return_array[] = array (
            'id' => $special_service['ServiceID'],
            'text' => $special_service['ServiceName']
          );
        }
      }
      
      return true;
    } else {
      return false;
    }
  } // function extract_classid


  ////
  // Extract Special Service name and ID pairs from the international response array
  //   Duplicates are removed, and there is no relation to which services are permitted
  function extract_international_serviceid( $response, &$return_array ) {
    if( is_array( $response ) && is_array( $response['Package']['Service'] ) ) {
      foreach ($response['Package']['Service'] as $extra_service) {
        // Check if there are any extra services
        if( isset( $extra_service['ExtraServices']['ExtraService'] ) && 
            is_array( $extra_service['ExtraServices']['ExtraService'] ) ) {
          if( isset( $extra_service['ExtraServices']['ExtraService']['ServiceID'] ) ) {        
            // There is only one extra service, so check to see if we already have it
            $already_in_array = check_service_in_array( $extra_service['ExtraServices']['ExtraService']['ServiceID'], $return_array );
            if( $already_in_array == false ) {
              // Extract the ServiceID and ServiceName values and add them to the output
              $return_array[] = array (
                'id' => $extra_service['ExtraServices']['ExtraService']['ServiceID'],
                'text' => $extra_service['ExtraServices']['ExtraService']['ServiceName']
              );
            }
          } else {
            // There is more than one extra service, so loop through them
            foreach( $extra_service['ExtraServices']['ExtraService'] as $extra_service_array ) {
              // check to see if we already have this service
              $already_in_array = check_service_in_array( $extra_service_array['ServiceID'], $return_array );
              if( $already_in_array == false ) {
                // Extract the ServiceID and ServiceName values and add them to the output
                $return_array[] = array (
                  'id' => $extra_service_array['ServiceID'],
                  'text' => $extra_service_array['ServiceName']
                );
              }
            }
          }
        }
      }
      
      return true;
    } else {
      return false;
    }
  } // function extract_classid
  
  
  ////
  // This function checks whether a Special/Extra service exists in the array of services
  // Used by extract_international_serviceid() and extract_domestic_serviceid()
  function check_service_in_array( $service_id, $services_array ) {
    if( count( $services_array ) > 0 ) {
      foreach( $services_array as $existing_value ) {
        if( $service_id == $existing_value['id'] ) {
          return true;
        }
      }
    }
    return false;
  }

?>