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

Crawler is written on PHP uses MySQL as a database and source placed under `crawler` folder.

Before running the script should be configured database and imported file `structure.sql`. 

After that rename file `.env.example` to `.env` and specify database credentials.

Usage: `php cmd crawl "http://www.nplg.gov.ge/gwdict/index.php"`

Help for options: `php cmd help crawl`

## TODO

Fix wrong entries and add more words

## License

Please see the [LICENSE](LICENSE.md) included in this repository for a full copy of the MIT license,
which this project is licensed under.
