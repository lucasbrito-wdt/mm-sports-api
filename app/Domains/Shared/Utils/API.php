<?php

namespace App\Domains\Shared\Utils;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class API
{
    /**
     * Base URL para os endpoints da API.
     */
    protected string $baseUrl;

    /**
     * Cabeçalhos HTTP padrões.
     */
    protected array $headers = [];

    /**
     * Parâmetros de consulta padrões a serem incluídos em todas as requisições.
     */
    protected array $queryParams = [];

    /**
     * Define um endpoint para ser implementado pelas classes concretas.
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Define ou substitui o token de autenticação no cabeçalho.
     */
    public function setToken(string $token): void
    {
        $this->headers['Authorization'] = 'Bearer ' . $token;
    }

    public function setAuthorizationBasic(string $username, string $password): void
    {
        $credentials = base64_encode("$username:$password");
        $this->headers['Authorization'] = "Basic $credentials";
    }

    /**
     * Adiciona ou substitui outros cabeçalhos HTTP.
     */
    public function setHeader(string $key, string $value): void
    {
        $this->headers[$key] = $value;
    }

    /**
     * Define ou adiciona parâmetros de consulta padrões para todas as requisições.
     *
     * @param  array  $params  Os parâmetros de consulta a serem definidos
     * @param  bool  $merge  Se true, mescla os novos parâmetros com os existentes; se false, substitui completamente
     */
    public function setQueryParams(array $params, bool $merge = true): void
    {
        if ($merge) {
            $this->queryParams = array_merge($this->queryParams, $params);
        } else {
            $this->queryParams = $params;
        }
    }

    /**
     * Realiza uma requisição HTTP GET.
     */
    public function get(string $uri, array $queryParams = []): Response
    {
        $url = $this->buildUrl($uri);
        $mergedParams = array_merge($this->queryParams, $queryParams);

        return Http::withHeaders($this->headers)->get($url, $mergedParams);
    }

    /**
     * Realiza uma requisição HTTP POST.
     */
    public function post(string $uri, array $data = []): Response
    {
        $url = $this->buildUrl($uri);

        return Http::withHeaders($this->headers)->post($url, $data);
    }

    /**
     * Realiza uma requisição HTTP PUT.
     */
    public function put(string $uri, array $data = []): Response
    {
        $url = $this->buildUrl($uri);

        return Http::withHeaders($this->headers)->put($url, $data);
    }

    /**
     * Realiza uma requisição HTTP ‘PATCH’.
     */
    public function patch(string $uri, array $data = []): Response
    {
        $url = $this->buildUrl($uri);

        return Http::withHeaders($this->headers)->patch($url, $data);
    }

    /**
     * Realiza uma requisição HTTP DELETE.
     */
    public function delete(string $uri, array $data = []): Response
    {
        $url = $this->buildUrl($uri);

        return Http::withHeaders($this->headers)->delete($url, $data);
    }

    /**
     * Constrói a URL completa a partir do base URL e do endpoint.
     */
    private function buildUrl(string $endpoint): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }
}
