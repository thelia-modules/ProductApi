<?php
/*************************************************************************************/
/*      This file is part of the module ProductAPI                                   */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace ProductAPI\Service;

use ProductAPI\ProductAPI;

class ApiClient
{
    public function getProduct($ref)
    {
        $request = "%host/api/product/$ref";

        return $this->apiCall($this->apiBuildRequest($request));
    }

    public function apiBuildRequest($request)
    {
        $host = ProductAPI::getServerHost();

        $request = str_replace('%host', $host, $request);

        return $request;
    }

    public function apiCall($request)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpcode >= 400) {
            throw new \Exception($response, $httpcode);
        }

        return json_decode($response);
    }
}