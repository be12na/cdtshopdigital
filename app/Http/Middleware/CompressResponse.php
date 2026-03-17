<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompressResponse
{
   public function handle(Request $request, Closure $next)
   {
      $response = $next($request);

      if (!$this->shouldCompress($request, $response)) {
         return $response;
      }

      $content = $response->getContent();
      if (!is_string($content) || $content === '') {
         return $response;
      }

      $compressed = gzencode($content, 6);
      if (!is_string($compressed) || $compressed === '') {
         return $response;
      }

      $response->setContent($compressed);
      $response->headers->set('Content-Encoding', 'gzip');
      $response->headers->set('Content-Length', (string) strlen($compressed));
      $response->headers->set('Vary', 'Accept-Encoding', false);

      return $response;
   }

   private function shouldCompress(Request $request, $response): bool
   {
      if (!$response instanceof Response) {
         return false;
      }

      if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
         return false;
      }

      if ($request->method() !== 'GET') {
         return false;
      }

      if ($response->getStatusCode() !== 200) {
         return false;
      }

      if ($response->headers->has('Content-Encoding')) {
         return false;
      }

      $acceptEncoding = (string) $request->headers->get('Accept-Encoding', '');
      if (stripos($acceptEncoding, 'gzip') === false) {
         return false;
      }

      $contentType = (string) $response->headers->get('Content-Type', '');
      if (stripos($contentType, 'application/json') === false && stripos($contentType, 'text/') === false) {
         return false;
      }

      $content = $response->getContent();
      if (!is_string($content) || strlen($content) < 1024) {
         return false;
      }

      return true;
   }
}

