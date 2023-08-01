# Product API

An API to get product from thelia based website with only the product's reference. 

## Installation

### Manually

* Copy the module into ```<thelia_root>/local/modules/``` directory and be sure that the name of the module is ProductAPI.
* Activate it in your thelia administration panel

## Usage

You can get product's information in JSON by calling the url : **/api/product**.

You have to add hash (in SHA1) of the parameters and the api key, to your request.

Generate your hash like this : [parameters][API KEY].

## Exemple

To get a product that has a reference of '*130010*' and your API key is '**ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2**',
you have to generate your hash like this :
- *130010***ExRtVQjUCCBApuN4s4fPEQ6i5yggYvm2** => 11dff8469f6f751b03c0e20c0c132c20fd3141f3

Then you call the API like this : 
- /api/product?ref=130010&hash=11dff8469f6f751b03c0e20c0c132c20fd3141f3

If a product with this ref exists and your hash is correct, the API should give you all product's informations
in JSON

### Input arguments

Arguments in **bold** are required.
Arguments in *italic* are optionals.

| Argument  | Description                                                           |
|-----------|-----------------------------------------------------------------------|
| **hash**  | The hash of the parameter and the API key                             |
| *country* | The country for the tax in ISO3                                       |
| *ref*     | The ref of the product (if you want to search a product with his ref) |
| *id*      | The product's ID (if you want to search a product with his ID)        |