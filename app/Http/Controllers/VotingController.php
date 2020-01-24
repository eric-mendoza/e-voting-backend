<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class VotingController extends Controller
{
    public function VoteToBlockchain(Request $request) {
        $uri = 'http://localhost:8888/v1';

        // Create client
        $client = new Client();
//        $client->setDefaultOption('headers/Content-Type', 'application/json');
        $response = $client
            ->post($uri.'/chain/abi_json_to_bin', [
                "json" => [
                    "code" => "voter",
                    "action" => "vote",
                    "args" => [
                        "id" => "1",
                        "dpi" => "DE507F0B2F4170D814922A2F29219D5C8936BBBF3ED460D68824CC2270455164",
                        "election_name" => "eleccion1",
                        "user_area" => "ciudad guatemala",
                        "candidates" => [
                            [
                                "key" => "presidente",
                                "value" => "patriota"
                            ]
                        ]
                    ]
                ]
            ]);

        // Obtener el binario del request a realizar.

        return $response->getBody();
    }
}
