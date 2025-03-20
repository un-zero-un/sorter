<?php

declare(strict_types=1);

namespace UnZeroUn\Sorter\Builder;

use Symfony\Component\HttpFoundation\Request;
use UnZeroUn\Sorter\Sorter;

final class QueryParamUrlBuilder implements UrlBuilder
{
    #[\Override]
    public function generateFromRequest(Sorter $sorter, Request $request, string $field, ?string $direction = null): string
    {
        $fieldPath = $sorter->getPath($field);

        if (null === $direction && $sorter->getCurrentSort()->has($fieldPath)) {
            $direction = 'ASC' === $sorter->getCurrentSort()->getDirection($fieldPath) ? 'DESC' : 'ASC';
        } elseif (null === $direction) {
            $direction = 'ASC';
        }

        $parsedUrl = parse_url($request->getUri());
        parse_str($parsedUrl['query'] ?? '', $query);

        $prefix = $sorter->getPrefix();

        foreach ($sorter->getFields() as $fieldName) {
            if (null !== $prefix) {
                unset($query[$prefix][$fieldName]);
                continue;
            }

            unset($query[$fieldName]);
        }

        if (null === $prefix) {
            $query[$field] = $direction;
        } else {
            $query[$prefix][$field] = $direction;
        }

        return ($parsedUrl['path'] ?? '').'?'.http_build_query($query);
    }
}
