<?php require_once('app.php'); ?> 
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Clubhouse CSV Import</title>
  <meta name="description" content="Import stories, epics and bugs to Clubhouse using a CSV file">
  <meta name="author" content="James Mikkelson">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="//fonts.googleapis.com/css?family=Raleway:400,300,600" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="css/normalize.css">
  <link rel="stylesheet" href="css/skeleton.css">
  <link rel="icon" type="image/png" href="images/favicon.png">
</head>
<body>

  <div class="container">
    <div class="row">
      <div class="one-half column" style="margin-top: 25%">
        <h4>Clubhouse CSV Import</h4>
        <p>This is an unofficial tool that allows you to import stories into Clubhouse.io from a CSV file using the <a href="https://clubhouse.io/api/v3" target="_blank">Clubhouse API V3</a>.</p>
        <p>You are free to <a href="https://github.com/mikkelson/clubhouse-csv-import" target="_blank">download the source code</a> for this tool.</p>
      </div>
    </div>
    
    <div class="row">
     <form action="index.php#resultContent" method="post" enctype="multipart/form-data">
      <input type='text' name='token' placeholder='Clubhouse API Token' required="required" value="<?=@$_POST['token'];?>"/>
       <br />
       <input type="file" name="csv" size="25" />
       <input type='submit' class='button-primary' value='Import CSV' />
    </form>
    </div>

    <?php /*Results Start*/ if(!empty($csv_lines)){?>
    <a name="resultContent"></a>
    <div class="row">
      <div class="column" style="margin-top: 5%">
        <h5>Results:</h5>
        
        <?php if(!empty($skipped)){?>
            <?=$skipped;?> of a total <?=$total;?> csv lines skipped because of missing required fields.
        <?php }?>

        <?php foreach($counts as $label => $c){?>
            <br /><?=$c;?> <?=$label;?>(s) imported successfully.
        <?php }?>
  

        <?php if(!empty($error_lines)){?>
            <br />The following <strong><?=$failed;?> CSV lines failed:</strong>
            <pre>
                <code>
                <?php foreach($error_lines as $l){?>
                    <?=$l."\n";?>
                <?php }?>
                </code>
            </pre>
        <?php }?>
        </div>
    </div>
    <?php /*Results End*/}?>

    <div class="row">
        <div class="column" style="margin-top: 5%">
            <h5>CSV Fields</h5>
            <p>Line 1 of your CSV must contain the field name(s) as below. <br/>The order of fields is not important. 
            <a href="example.csv">Download example.csv</a></p>
            <ul>
              <li>
                Required Fields
                <ul>
                  <li>project_id</li>
                  <li>name <em>(The title of this story)</em></li>
                  <li>story_type <em>(options: feature, chore, bug)</em></li>
                </ul>
              </li>
              <li>
                Optional Fields
                <ul>
                	<li>epic_id</li>
                	<li>external_id</li>
                	<li>labels</li>
                	<li>external_links</li>
                	<li>workflow_state_id</li>
                    <li>milestone_id</li>
                    <li>description</li>
                    <li>estimate</li>
                    <li>owner_ids <em>(Space delimitted list of owner UUID)</em></li>
                    <li><em>See a complete <a href="https://clubhouse.io/api/rest/v3/#Stories" target="_blank">list of available fields</a>.</em></li>
                </ul>
              </li>
            </ul>
         </div>
    </div>

    <div class="row">
      <div class="one-half column" style="margin-top: 5%">
        <p>Feedback and bug reports welcome <a href="https://twitter.com/happyrailfail" target="_blank">@happyrailfail</a>.</p>
      </div>
    </div>
    
  </div>
<a href="https://github.com/mikkelson/clubhouse-csv-import" class="github-corner" aria-label="View source on Github"><svg width="80" height="80" viewBox="0 0 250 250" style="fill:#FD6C6C; color:#fff; position: absolute; top: 0; border: 0; right: 0;" aria-hidden="true"><path d="M0,0 L115,115 L130,115 L142,142 L250,250 L250,0 Z"></path><path d="M128.3,109.0 C113.8,99.7 119.0,89.6 119.0,89.6 C122.0,82.7 120.5,78.6 120.5,78.6 C119.2,72.0 123.4,76.3 123.4,76.3 C127.3,80.9 125.5,87.3 125.5,87.3 C122.9,97.6 130.6,101.9 134.4,103.2" fill="currentColor" style="transform-origin: 130px 106px;" class="octo-arm"></path><path d="M115.0,115.0 C114.9,115.1 118.7,116.5 119.8,115.4 L133.7,101.6 C136.9,99.2 139.9,98.4 142.2,98.6 C133.8,88.0 127.5,74.4 143.8,58.0 C148.5,53.4 154.0,51.2 159.7,51.0 C160.3,49.4 163.2,43.6 171.4,40.1 C171.4,40.1 176.1,42.5 178.8,56.2 C183.1,58.6 187.2,61.8 190.9,65.4 C194.5,69.0 197.7,73.2 200.1,77.6 C213.8,80.2 216.3,84.9 216.3,84.9 C212.7,93.1 206.9,96.0 205.4,96.6 C205.1,102.4 203.0,107.8 198.3,112.5 C181.9,128.9 168.3,122.5 157.7,114.1 C157.9,116.9 156.7,120.9 152.7,124.9 L141.0,136.5 C139.8,137.7 141.6,141.9 141.8,141.8 Z" fill="currentColor" class="octo-body"></path></svg></a><style>.github-corner:hover .octo-arm{animation:octocat-wave 560ms ease-in-out}@keyframes octocat-wave{0%,100%{transform:rotate(0)}20%,60%{transform:rotate(-25deg)}40%,80%{transform:rotate(10deg)}}@media (max-width:500px){.github-corner:hover .octo-arm{animation:none}.github-corner .octo-arm{animation:octocat-wave 560ms ease-in-out}}</style>
</body>
</html>
