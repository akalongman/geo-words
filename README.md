# Georgian (ka_GE) word list

Download in: 
[DIC](https://github.com/akalongman/geo-words/raw/master/dictionary/dic/ka_GE.dic) | 
[TXT](https://github.com/akalongman/geo-words/raw/master/dictionary/txt/ka_GE.txt) |
[SQL](https://github.com/akalongman/geo-words/raw/master/dictionary/sql/ka_GE.sql)

## Data sources

- Kevin Scannell (http://crubadan.org/languages/ka, CC-BY 4.0) 
- National Parliamentary Library of Georgia (http://www.nplg.gov.ge/gwdict/index.php)
- Other Georgian eBooks/websites ([Crawler](#crawler))

## Crawler

Crawler is written on PHP and uses MySQL as a database. Code placed under `crawler` folder.

Before running the script should be configured database and run migrations. 

First of all rename the file `.env.example` to `.env` and specify database credentials.

Install composer dependencies:

    composer install

And run migrations:

    composer migrate  

Usage: `php cmd crawl "http://www.nplg.gov.ge/gwdict/index.php"`

Help for options: `php cmd help crawl`

## TODO

- Fix wrong entries and add more words
- Add tests
- Add notification sending on complete

## License

Please see the [LICENSE](LICENSE.md) included in this repository for a full copy of the MIT license,
which this project is licensed under.
