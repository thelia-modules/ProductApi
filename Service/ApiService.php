<?php


namespace ProductAPI\Service;


use ProductAPI\ProductAPI;
use Symfony\Component\HttpFoundation\Request;

class ApiService
{
    public function verifyHash(Request $request): bool
    {
        $apiKey = ProductAPI::getConfigValue('productapi_key', ProductAPI::API_KEY);

        $parameters = $request->query->all();
        unset($parameters['hash']);
        $values = implode($parameters);

        return $request->query->get('hash') === sha1($values . $apiKey);
    }
}