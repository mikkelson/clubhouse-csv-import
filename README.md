# Clubhouse.io CSV Import

This is a simple PHP app that turns a CSV file into stories *(bug, chore, feature)* using the [Clubhouse v1 API](https://clubhouse.io/api/v1/).

There is no framework or package manager, just a few lines of PHP and a pleasant UI built on the [Skeleton CSS framework](http://www.getskeleton.com)

# Preview

![Clubhouse CSV Import Tool](https://raw.githubusercontent.com/mikkelson/clubhouse-csv-import/master/images/preview.PNG)

* external_id
* epid_id (must map to an existing Epic ID)
* labels (comma-separated list of the labels to attach)
 
# Installation & Usage

Clone this repository to a location available by your webserver and load index.php in the browser. 

If you do not want to install the app, a hosted version is available: [Clubhouse CSV Importer](http://jamesmikkelson.com/clubhouse)
