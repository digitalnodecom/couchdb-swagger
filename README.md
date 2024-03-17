# CouchDB Swagger Generator

This PHP CLI script will scrape [CouchDB API documentation](https://docs.couchdb.org/en/stable/api/index.html) and generate a Swagger 3.0 file. You can find the file in this repository as well under couchdb.json. 

## Usage

If you want to generate the file yourself, you can run the following command:
```php
php cli.php > couchdb.json
```

## Notes

- /{db}/_design/{ddoc}/_rewrite/{path} uses the HTTP method 'any'. This is not supported and we replaced it with _post_.
- Some of the API calls have the HTTP method 'copy'. These have been removed.  
