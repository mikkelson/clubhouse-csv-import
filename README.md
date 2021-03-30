# Clubhouse.io CSV Import

This is a simple PHP app that turns a CSV file into stories *(bug, chore, feature)* using the [Clubhouse v3 API](https://clubhouse.io/api/v3/).

There is no framework or package manager, just a few lines of PHP and a pleasant UI built on the [Skeleton CSS framework](http://www.getskeleton.com)

# Preview

![Clubhouse CSV Import Tool](https://raw.githubusercontent.com/mikkelson/clubhouse-csv-import/master/images/preview.PNG)

If you do not want to install the app, a hosted version is available: [Clubhouse CSV Importer](http://jamesmikkelson.com/clubhouse)

## Supported Fields

### Required Fields

* project_id
* name (The title of this story)
* story_type (options: feature, chore, bug)

### Optional Fields
* epic_id (must be a pre-existing Epic)
* external_id
* labels (comma-separated list of the labels to attach)
* external_links
* workflow_state _id
* milestone_id
* description
* estimate
* owner_ids (Space delimited list of owner UUID)

See a complete <a href="https://clubhouse.io/api/rest/v3/#Stories" target="_blank">list of available fields</a>.
 
# Installation using an existing server

Clone this repository to a location available by your webserver and load index.php in the browser. 

# Installation via Docker
1. Install Docker on your machine
1. Clone this repository to any location on your system
2. run `docker-compose up -d` in the repository folder
3. Visit `localhost:8080`, the importer is available there

# Usage for Migrating Stories between Clubhouse Workspaces
1. Download the epic as a csv file
2. Create an API Token in the target workspace and save it somewhere safe
3. Create an epic in the target workspace and remember the id
4. Create Projects (if not existant) in the target workspace and remember the ids
5. Open the CSV file in a spreadsheet tool (e.g. Google Sheets)
6. Change the column title `type` to `story_type`
7. Replace the epic ids from the origin workspace with the epic id from the target workspace
8. Do the same for project ids
9. Paste the API token in the token field in the importer
10. Upload the file and hit import
