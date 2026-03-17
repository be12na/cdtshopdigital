<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConditionalGet
{
   public function handle(Request $request, Closure $next)
   {
      $response = $next($request);

      if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
         return $response;
      }

      if (!$response instanceof Response) {
         return $response;
      }

      if ($response->getStatusCode() !== 200) {
         return $response;
      }

      if ($response->headers->has('ETag')) {
         $ifNoneMatch = (string) $request->header('If-None-Match', '');
         if ($ifNoneMatch !== '') {
            $etag = (string) $response->headers->get('ETag');
            if ($this->etagMatches($ifNoneMatch, $etag)) {
               $response->setNotModified();
               return $response;
            }
         }
      }

      return $response;
   }

   private function etagMatches(string $ifNoneMatchHeader, string $etagHeader): bool
   {
      $etagHeader = trim($etagHeader);
      if ($etagHeader === '') {
         return false;
      }

      $candidates = array_map('trim', explode(',', $ifNoneMatchHeader));
      foreach ($candidates as $candidate) {
         if ($candidate === '*' || $this->normalizeEtag($candidate) === $this->normalizeEtag($etagHeader)) {
            return true;
         }
      }

      return false;
   }

   private function normalizeEtag(string $etag): string
   {
      $etag = trim($etag);
      if (str_starts_with($etag, 'W/')) {
         $etag = trim(substr($etag, 2));
      }
      $etag = trim($etag);
      if (str_starts_with($etag, '"') && str_ends_with($etag, '"') && strlen($etag) >= 2) {
         $etag = substr($etag, 1, -1);
      }
      return trim($etag);
   }
}

