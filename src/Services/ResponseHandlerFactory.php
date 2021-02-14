<?php
/**
 * Date: 13.02.21
 * Time: 23:05
 */
namespace App\Services;

use App\Services\Interfaces\CrawlerWorker;
use App\Services\Interfaces\ResponseHandler;

class ResponseHandlerFactory
{
    /**
     * @param CrawlerWorker $currentCrawler
     * @return ResponseHandler
     */
    public static function makeResponseHandler(CrawlerWorker $currentCrawler): ResponseHandler
    {
        switch($currentCrawler->getTaskDto()->getResponseHandlerType()) {
            case "LINKS_DIGGER":
                $handler = new LinksDigger($currentCrawler);
                break;
            case "FILES_HANDLER":
                $handler = new FilesHandler($currentCrawler);
                break;
            default:
                throw new \InvalidArgumentException("Unknown response handler type");
        }
        return $handler;
    }

}
