<?php


namespace ProductApi\Service;


use ProductApi\ProductApi;
use Symfony\Component\HttpFoundation\Request;

class ApiService
{
    public function verifyHash(Request $request)
    {
        $apiKey = ProductApi::getConfigValue('productapi_key', ProductApi::API_KEY);

        $parameters = $request->query->all();
        unset($parameters['hash']);
        $values = implode($parameters);

        return $request->query->get('hash') === sha1($values . $apiKey);
    }
}