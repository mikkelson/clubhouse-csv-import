<?php
/**
 * @link          https://github.com/mikkelson/clubhouse-csv-import
 * @author        James Mikkelson
 */
ini_set('error_reporting', 0);

if (!empty($_FILES['csv']['name']) && substr($_FILES['csv']['name'], -4) == '.csv') {

    if (!$_FILES['csv']['error']) {
        $skipped = 0;
        $count = 0;
        $failed = 0;

        $csv_lines = unpackCsv($_FILES['csv']['tmp_name']);

        //loop through csv lines and add to clubhouse as stories
        $total = count($csv_lines);
        $apiLimit = 200;
        $apiCount = 0;
        foreach ($csv_lines as $line) {
            $count++;
            $apiCount++;
            if ($apiCount == $apiLimit) {
                //sleep for a 60 seconds, avoid Clubhouse API limit
                sleep(60);
                $apiCount = 0;
            }

            //project_id, name and story_type are required by the Clubhouse API
            if (empty($line['project_id']) || empty($line['name']) || empty($line['story_type'])) {
                $error_lines[] = "Line " . $count . " is missing required field <i>project_id</i>, <i>name</i> or <i>story_type</i>";
                $skipped++;
                $failed++;
                continue;
            }

	        // Required columns
            $payload = array("project_id" => $line['project_id'],
                              "name" => $line['name'],
                              "story_type" => $line['story_type']
            );
            
            // Optional columns
            addIfNotEmpty('milestone_id', $line, $payload);
            addIfNotEmpty('description', $line, $payload);
            addIfNotEmpty('estimate', $line, $payload);
            addIfNotEmpty('epic_id', $line, $payload);
            addIfNotEmpty('external_id', $line, $payload);
            addIfNotEmpty('requested_by_id', $line, $payload);
            addIfNotEmptyAsArray('owner_ids', $line, ' |;|,|\n', $payload);
            addIfNotEmptyAsHash('labels', $line, ';|,|\n', 'name', $payload);
            addIfNotEmptyAsArray('external_links', $line, ' |;|,|\n', $payload);
            addIfNotEmpty('external_id', $line, $payload);
            addIfNotEmpty('workflow_state_id', $line, $payload);
            addIfNotEmptyAsTasks('tasks', $line, $payload);

            if (isset($payload['owner_ids']) && isset($payload['tasks'])) {
                foreach ($payload['tasks'] as &$task)
                    $task['owner_ids'] = $payload['owner_ids'];
            }
            $data = json_encode($payload);

            //make Clubhouse POST request
            $result = postClubhouse($_POST['token'], $data);
            if (!empty($result->created_at)) {
                @$counts[$line['story_type']] ++;
            } elseif (!empty($result->message)) {
                $error_lines[] = "Line " . $count . ": <em>" . $line['name'] . "</em> failed: " . $result->message . "";
                $failed++;
            } else {
                $error_lines[] = "Line " . $count . ": <em>" . $line['name'] . "</em> failed: Unexpected error";
                $failed++;
            }
        }
    } else {
        echo 'CSV upload failed: ' . $_FILES['csv']['error'];
    }
}

/**
  *
  * If $src['key'] value is not empty, add a $key with the value to $dest
  *
  */
function addIfNotEmpty($key, $src, &$dest) {
    if (isNotEmptyString($src[$key])) $dest[$key] = $src[$key];
}

/**
  *
  * If $src['key'] value is not empty, explode to array (w/ $delim as delimeter),
  * and add a $key with the array as its value to $dest.
  *
  */
function addIfNotEmptyAsArray($key, $src, $delim, &$dest)
{
    if (isNotEmptyString($src[$key]))
        $dest[$key] = preg_split('/ *(' . $delim . ') */', $src[$key]);
}

/**
 *
 * If $src['key'] value is not empty, explode to array (w/ $delim as delimeter),
 * and add a $key with a hash (associative array) of the values  as its value to
 * $dest, using $secondkey as the internal key
 *
 */
function addIfNotEmptyAsHash($key, $src, $delim, $secondkey, &$dest)
{
    if (isNotEmptyString($src[$key])) {
        $hash = array();
        $values = preg_split('/ *(' . $delim . ') */', $src[$key]);
        foreach ($values as $item) {
            $hash[] = array($secondkey => $item);
        }
        $dest[$key] = $hash;
    }
}

function addIfNotEmptyAsTasks($key, $src, &$dest)
{
    if (isNotEmptyString($src[$key])) {
        // Export starts with "[ ] " or "[X] ", delimited by ";[ ] " or ";[X] "
        // Other options:
        //    Start/delmited with "*" or "-" maybe surrounded by spaces/line breaks
        //    Delimited by numbers, maybe surrounded by spaces/line breaks
        //    Delimited by ";" maybe surrounded by spaces/line breaks
        if (preg_match('/^\[[ X]\]/', $src[$key])) {
            $task_list_string = preg_replace('/^\[[ X]\] */', '', $src[$key]);
            $task_list = preg_split('/;\[[ X]\] */', $task_list_string);
        } else if (preg_match('/^ *[*-]]/', $src[$key])) {
            $task_list_string = preg_replace('/^ *[*-] */', '', $src[$key]);
            $task_list = preg_split('/[,;\n ]*[*-] */', $task_list_string);
        } else if (preg_match('/^\d+[\. :-]]/', $src[$key])) {
            $task_list_string = preg_replace('/^\d+[\.:-] */', '', $src[$key]);
            $task_list = preg_split('/[,;\n ]*\d+[\.:-] */', $task_list_string);
        } else {
            $task_list = preg_split('/;[\n ]*/', $src[$key]);
        }
        if (count($task_list) > 0) $dest[$key] = array_map(fn($task) => array('description' => $task), $task_list);
    }
}

function isNotEmptyString($str){
    return (isset($str) && (strlen(trim($str)) > 0));
}

function unpackCsv($csv) {

    $row = 1;
    $csv_lines = array();
    if (($handle = fopen($csv, "r")) !== false) {
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $num = count($data);
            for ($c = 0; $c < $num; $c++) {
                if ($row == 1) {
                    //first row, map columns to Clubhouse API fields
                    $api_field_mapping[] = $data[$c];
                } else {
                    @$csv_lines[$row][$api_field_mapping[$c]] = $data[$c];
                }
            }

            $row++;
        }
        fclose($handle);
    }
    return $csv_lines;
}

function postClubhouse($token, $data) {

    $story_url = 'https://api.clubhouse.io/api/v3/stories';

    $ch = curl_init($story_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Clubhouse-Token: ' . $token,
            'Content-Length: ' . strlen($data))
    );

    $result = curl_exec($ch);
    curl_close($ch);

    return json_decode($result);
}