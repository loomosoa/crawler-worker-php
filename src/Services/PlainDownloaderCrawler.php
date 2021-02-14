<?php
declare(strict_types=1);
/**
 * Date: 12.02.21
 * Time: 22:26
 */
namespace App\Services;

use App\Services\Abstracts\BaseCrawlerWorker;

use App\Services\Inventory\PlainDownloaderDto;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

/**
 * Crawler based on Guzzle, aimed to make concurrent plain downloads of URL (pages, images, etc.)
 *
 * Class PlainDownloaderCrawler
 * @package App\Services
 */
class PlainDownloaderCrawler extends BaseCrawlerWorker
{

    /**
     * Initialize specific crawler params and settings: headers, etc. based on TaskDto data
     */
    public function configure(): void
    {
        parent::configure();
    }

    /**
     * @inheritdoc
     */
    public function crawl(): void
    {
        $client = new Client();
        $requests = $this->prepareRequests();
        $pool = $this->makePool($client, $requests);
        $promise = $pool->promise();
        $promise->wait();
    }

    /**
     * @return callable
     */
    protected function prepareRequests(): callable
    {
        $requests = function () {
            foreach ($this->taskDto->getUrls() as $url) {
                yield new Request('GET', $url);
            }
        };
        return $requests;
    }

    /**
     * @param $client
     * @param callable $requests
     * @return Pool
     */
    protected function makePool($client, callable $requests): Pool
    {
        return new Pool($client, $requests(), [
            'concurrency' => $this->taskDto->getConcurrencyValue(),
            'options' => [
                'debug' => $this->taskDto->getOptions()->debug
            ],
            'fulfilled' => function (Response $response, $index) {
                $this->successfulRequestsQuantity++;
                $this->crawlSuccessfully($response, $index);
            },
            'rejected' => function (RequestException $reason, $index) {
                $this->rejectedRequestsQuantity++;
                $this->crawlRejected($reason, $index);
            },
        ]);
    }

    protected function crawlSuccessfully(Response $response, $requestIndex): void
    {
        $crawlerDto = new PlainDownloaderDto();
        $crawlerDto->setResponse($response);
        $crawlerDto->setRequestIndex($requestIndex);
        $crawlerDto->setRequestedUrl($this->getRequestedUrl($requestIndex));

        $responseHandler = ResponseHandlerFactory::makeResponseHandler($this);
        $responseHandler->setCrawlerDto($crawlerDto);
        $responseHandler->handleSuccessfulResponse();
    }

    protected function getRequestedUrl(int $requestIndex): string
    {
        return $this->taskDto->getUrls()[$requestIndex];
    }

    protected function crawlRejected(RequestException $reason, $requestIndex): void
    {
        $crawlerDto = new PlainDownloaderDto();
        $crawlerDto->setRequestException($reason);
        $crawlerDto->setRequestIndex($requestIndex);
        $crawlerDto->setRequestedUrl($this->getRequestedUrl($requestIndex));

        $responseHandler = ResponseHandlerFactory::makeResponseHandler($this);
        $responseHandler->setCrawlerDto($crawlerDto);
        $responseHandler->handleRejectedResponse();
    }
}
