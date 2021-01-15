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

Before running the script should be configured the database and run migrations. 

First rename the file `.env.example` to `.env` and specify database credentials.

Install composer dependencies:

    composer install

And run migrations:

    composer migrate  

## Usage 

### Crawl links with `internal` profile
This command will crawl urls only inside specified domain and ignore external urls

    php cmd crawl --profile=internal "http://www.nplg.gov.ge/gwdict/index.php"

### Crawl links with `all` profile
This command will crawl all links

    php cmd crawl --profile=all "http://www.nplg.gov.ge/gwdict/index.php"

### Crawl links with `domain` profile
This command will crawl links with all domains, which end with `--domain`

    php cmd crawl --profile=domain --domain=.ge "http://www.nplg.gov.ge/gwdict/index.php"

Will be crawled links, where url's domain ends with `.ge` suffix

### Crawl links with `subset` profile
This command will crawl all urls if link starts with `--subset`

    php cmd crawl --profile=subset --subset="http://www.nplg.gov.ge/gwdict/index.php?a=list&d=46" "http://www.nplg.gov.ge/gwdict/index.php?a=list&d=46"

Will be crawled links, where url starts with `www.nplg.gov.ge/gwdict/index.php?a=list&d=46` prefix


Show all possible options: `php cmd help crawl`

## TODO

- Fix wrong entries and add more words
- Add tests
- Add notification sending on complete

## License

Please see the [LICENSE](LICENSE.md) included in this repository for a full copy of the MIT license,
which this project is licensed under.
