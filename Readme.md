# Product API

An API to get product from thelia based website with only the product's reference. 

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is ProductAPI.
* Activate it in your thelia administration panel

### Composer

Add it in your main thelia composer.json file

```
composer require your-vendor/product-api-module:~1.0
```

## Usage

To get product information in JSON, call this URL : http://[YOUR-WEBSITE]/admin/module/productapi/search?q=[REFERENCE]
Call the API URL from anywhere to get product's information.
The API return a JSON format of the product.
If no product is found with the reference, the API return null.

### Input arguments

|Argument |Description |
|---      |--- |
|**q** | The reference searched |
