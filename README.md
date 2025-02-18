# Taiwan Preschools Data Archive

This repository contains archived data from the National Early Childhood Educare system in Taiwan.

## Data Source

The data is sourced from the [National Early Childhood Educare Web System](https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx) (全國教保資訊網).

## Visualization

The data visualization can be accessed at: https://kiang.github.io/preschools/

## Scripts

The data is collected and processed using several PHP scripts:

* `scripts/01_crawler.php` - Simulates user search to collect basic information of all preschools
* `scripts/01_map_raw.php` - Retrieves geographical positions of each preschool
* `scripts/01_punish_daily.php` - Collects punishment records issued by local administrations
* `scripts/slip.php` - Gathers fee information for each preschool by age group

## License

- Code is released under the MIT License
- Data is compatible with CC-BY license. When using this data, please attribute to: https://ap.ece.moe.edu.tw/webecems/pubSearch.aspx
