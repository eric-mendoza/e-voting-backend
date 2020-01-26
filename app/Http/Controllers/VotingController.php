<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class VotingController extends Controller
{
    public function VoteToBlockchain(Request $request) {
        $uri = 'http://localhost:8888/v1';
        $wallet_uri = 'http://localhost:8900/v1';
        $expiration = Carbon::now()->addMinute();

        // 1) Create client
        $client = new Client();

        // 2) Convert to binary the request
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
        $binary = json_decode($response->getBody())->binargs;

        // 3) Obtain block data to post to
        $response = $client
            ->get($uri.'/chain/get_info');

        $response = json_decode($response->getBody());
        $block_num = $response->head_block_num;
        $blockchain_name = $response->chain_id;

        // 4) Obtain block data
        $response = $client
            ->post($uri.'/chain/get_block', [
                "json" => [
                    "block_num_or_id" => $block_num,
                ]
            ]);

        $response = json_decode($response->getBody());
        $block_num_ref = $response->ref_block_prefix;

        // 4) Get required keys
        $response = $client
            ->post($uri.'/chain/get_required_keys', [
                "json" => [
                    "transaction" => [
                        "expiration" => $expiration,
                        "ref_block_num" => $block_num,
                        "ref_block_prefix" => $block_num_ref,
                        "max_net_usage_words" => "0",
                        "max_cpu_usage_ms" => "0",
                        "delay_sec" => 0,
                        "context_free_actions" => [],
                        "actions" => [
                            [
                                "account" => "voter",
                                "name" => "vote",
                                "authorization" => [
                                    [
                                        "actor" => "generaluser",
                                        "permission" => "active",
                                    ]
                                ],
                                "data" => $binary,
                                "hex_data" => ""
                            ]
                        ],
                        "transaction_extensions" => []
                    ],
                    "available_keys" => [
                        "EOS5cjyPsYg5RaukWkzpfivhwFFeBQxr5xnYTEJbn1PhFZAQEreKa",
                        "EOS6MRyAjQq8ud7hVNYcfnVPJqcVpscN5So8BhtHuGYqET5GDW5CV",
                        "EOS8LDYNXahGLPVrVAc5iqJQVpWkp7sXT7V2Rpa8osDCUDiF1i5Hn"
                    ]
                ]
            ]);

        $response = json_decode($response->getBody());
        $key = $response->required_keys[0];

        // 5) Try to unlock wallet
        try {
            $response = $client
                ->post($wallet_uri.'/wallet/unlock', [
                    "json" => [
                        "evote_wallet",
                        "PW5JknmHPvxZR6oKgBX9dT3aHvgmGvTLxpbyHy7Czx2d7dF4QCbue",
                    ]
                ]);

        } catch (RequestException $e) {
            $response = "ok";
        }

        // 6) Sign transaction
        $response = $client
            ->post($wallet_uri.'/wallet/sign_transaction', [
                "json" => [
                    [
                        "expiration" => $expiration,
                        "ref_block_num" => $block_num,
                        "ref_block_prefix" => $block_num_ref,
                        "actions" => [
                            [
                                "account" => "voter",
                                "name" => "vote",
                                "authorization" => [
                                    [
                                        "actor" => "generaluser",
                                        "permission" => "active",
                                    ]
                                ],
                                "data" => $binary,
                            ]
                        ],
                        "signatures" => [],
                    ],
                    [
                        $key
                    ],
                    $blockchain_name
                ]
            ]);
        $response = json_decode($response->getBody());
        $signature = $response->signatures[0];

        // 7) Push transactions
        $response = $client
            ->post($uri.'/chain/push_transaction', [
                "json" => [
                    "compression" => "none",
                    "transaction" => [
                        "expiration" => $expiration,
                        "ref_block_num" => $block_num,
                        "ref_block_prefix" => $block_num_ref,
                        "context_free_actions" => [],
                        "actions" => [
                            [
                                "account" => "voter",
                                "name" => "vote",
                                "authorization" => [
                                    [
                                        "actor" => "generaluser",
                                        "permission" => "active",
                                    ]
                                ],
                                "data" => $binary,
                            ]
                        ],
                        "transaction_extensions" => []
                    ],
                    "signatures" => [
                        $signature
                    ]
                ]
            ]);
        $response = json_decode($response->getBody());


        return json_encode(["ok" => $response->transaction_id]);
    }
}
