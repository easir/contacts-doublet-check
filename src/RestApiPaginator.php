<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;

final class RestApiPaginator
{
    /** @var GuzzleClient */
    private $client;
    /** @var array|null */
    private $headers;

    public function __construct(GuzzleClient $client, ?array $headers = null)
    {
        $this->client = $client;
        $this->headers = $headers;
    }

    /**
     * @throws GuzzleException
     */
    public function get(string $url): Generator
    {
        return $this->request('GET', $url);
    }

    /**
     * @param array|mixed[] $payload
     * @throws GuzzleException
     */
    public function post(string $url, array $payload): Generator
    {
        return $this->request('POST', $url, $payload);
    }

    /**
     * @param array|mixed[] $payload
     * @throws GuzzleException
     */
    private function request(string $method, string $url, array $payload = []): Generator
    {
        $page = 0;
        $options = ['json' => $payload];
        if ($this->headers) {
            $options['headers'] = $this->headers;
        }
        do {
            $page++;
            $response = $this->client->request(
                $method,
                sprintf('%s?page=%d', $url, $page),
                $options
            );

            ['data' => $data, 'pagination' => $pagination] = json_decode((string) $response->getBody(), true);

            yield from $data;
        } while ($pagination['urls']['next'] !== null);
    }
}
