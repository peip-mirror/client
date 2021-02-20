<?php

/**
 * This file is part of the k8s/client library.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace K8s\Client\Http;

use K8s\Client\Exception\RuntimeException;
use K8s\Client\Options;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;

class RequestFactory
{
    public const CONTENT_TYPE_JSON = 'application/json';

    private const ACTION_MAP = [
        'post' => 'POST',
        'get' => 'GET',
        'get-status' => 'GET',
        'patch' => 'PATCH',
        'patch-status' => 'PATCH',
        'put' => 'PUT',
        'put-status' => 'PUT',
        'connect' => 'GET',
        'delete' => 'DELETE',
        'deletecollection' => 'DELETE',
        'list' => 'GET',
        'watch' => 'GET',
        'watchlist' => 'GET',
    ];

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var UriFactoryInterface
     */
    private $uriFactory;

    /**
     * @var Options
     */
    private $options;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        UriFactoryInterface  $uriFactory,
        Options $options
    ) {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->uriFactory = $uriFactory;
        $this->options = $options;
    }

    public function makeRequest(
        string $uri,
        string $action,
        ?string $acceptType = null,
        ?string $body = null,
        ?string $contentType = null,
        ?string $httpMethod = null
    ): RequestInterface {
        $httpMethod = $httpMethod ?? self::ACTION_MAP[$action] ?? null;

        if ($httpMethod === null) {
            throw new RuntimeException(sprintf(
                'The action "%s" has no recognized HTTP method.',
                $action
            ));
        }
        $request = $this->requestFactory->createRequest(
            strtoupper($httpMethod),
            $uri
        );

        if ($body) {
            $request = $request
                ->withBody($this->streamFactory->createStream($body))
                ->withHeader(
                    'Content-type',
                    $contentType ?? self::CONTENT_TYPE_JSON
                );
        }

        if ($acceptType) {
            $request = $request->withHeader(
                'Accept',
                $acceptType
            );
        }

        return $this->addAuthIfNeeded($request);
    }

    public function makeFromRequest(string $uri, RequestInterface $request): RequestInterface
    {
        return $this->addAuthIfNeeded($request)
            ->withUri($this->uriFactory->createUri($uri));
    }

    private function addAuthIfNeeded(RequestInterface $request): RequestInterface
    {
        if ($this->options->getAuthType() === Options::AUTH_TYPE_TOKEN && $this->options->getToken()) {
            $request = $request->withHeader(
                'Authorization',
                sprintf('Bearer %s', $this->options->getToken())
            );
        } elseif ($this->options->getAuthType() === Options::AUTH_TYPE_BASIC) {
            $request = $request->withHeader(
                'Authorization',
                sprintf(
                    'Basic %s',
                    base64_encode("{$this->options->getUsername()}:{$this->options->getPassword()}")
                )
            );
        }

        return $request;
    }
}
