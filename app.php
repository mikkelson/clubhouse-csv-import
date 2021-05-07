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
        $members = array();
        $workflow_states = array();

        $csv_lines = unpackCsv($_FILES['csv']['tmp_name']);

        //loop through csv lines and add to clubhouse as stories
        $total = count($csv_lines);
        $apiLimit = 200;
        $apiCount = 0;

        // If user columns, get member list to translate to UUID's
        if (isset($csv_lines[0]) && array_key_exists('owners', $csv_lines[0])) {
            $return = reqClubhouse('members', $_POST['token'], null);
            foreach ($return as $member) {
                $members[$member->profile->email_address] = $member->id;
            }
        }

        // If workflow state column, get list of workflow states to translate to ID's
        if (isset($csv_lines[0]) && array_key_exists('state', $csv_lines[0])) {
            $return = reqClubhouse('workflows', $_POST['token'], null);
            foreach ($return as $workflow) {
                foreach ($workflow->project_ids as $project_id) {
                    foreach ($workflow->states as $state) {
                        $workflow_states[$project_id][$state->name] = $state->id;
                    }
                }
            }
        }

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
//            addIfNotEmpty('milestone_id', $line, $payload);
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
            addIfNotEmptyAsMemberArray('owners', 'owner_ids', $line, $members, $payload);
            addIfNotEmptyAsMember('requester', 'requested_by_id', $line, $members, $payload);
            addIfNotEmptyAsWorkflowState('state', 'workflow_state_id', $line, $workflow_states, $payload);

            if (isset($payload['owner_ids']) && isset($payload['tasks'])) {
                foreach ($payload['tasks'] as &$task)
                    $task['owner_ids'] = $payload['owner_ids'];
            }
            $data = json_encode($payload);

            //make Clubhouse POST request
            $result = reqClubhouse('stories', $_POST['token'], $data);
            if (!empty($result->created_at)) {
                @$counts[$line['story_type']]++;
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

function addIfNotEmptyAsMemberArray($key_from, $key_to, $src, $members, &$dest)
{
    if (isNotEmptyString($src[$key_from]) && !isNotEmptyString($src[$key_to])) {
        $member_split = preg_split('/[,; ] */', $src[$key_from]);
        for ($i = 0; $i < count($member_split); $i++)
            $member_split[$i] = $members[$member_split[$i]];
        $dest[$key_to] = array_filter($member_split);
    }
}

function addIfNotEmptyAsMember($key_from, $key_to, $src, $members, &$dest)
{
    if (isNotEmptyString($src[$key_from]) && !isNotEmptyString($src[$key_to]))
        $dest[$key_to] = $members[$src[$key_from]];
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

function addIfNotEmptyAsWorkflowState($key_from, $key_to, $src, $workflow_states, &$dest)
{
    if (isNotEmptyString($src[$key_from]) && !isNotEmptyString($src[$key_to]))
        $dest[$key_to] = $workflow_states[$src['project_id']][$src[$key_from]];
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
                    @$csv_lines[$row - 2][$api_field_mapping[$c]] = $data[$c];
                }
            }

            $row++;
        }
        fclose($handle);
    }
    return $csv_lines;
}

function reqClubhouse($service, $token, $data)
{
    $endpoint_url = 'https://api.clubhouse.io/api/v3/' . $service;

    $ch = curl_init($endpoint_url);
    if ($data) {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } else {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    }
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