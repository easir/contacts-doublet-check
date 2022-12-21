<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

final class RestApiPaginator
{
    /**
     * @param array|mixed[]|null $headers
     */
    public function __construct(
        private readonly Client $client,
        private readonly array|null $headers = null
    ) {
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

        $check = parse_url($url, PHP_URL_QUERY);
        $pattern = empty($check) ? '?' : '&';

        do {
            $page++;
            $url = sprintf('%s%spage=%d', $url, $pattern, $page);
            $response = $this->client->request($method, $url, $options);
            ['data' => $data, 'pagination' => $pagination] = json_decode((string) $response->getBody(), true);

            yield from $data;
        } while ($pagination['urls']['next'] !== null);
    }
}
