<?php

namespace AhmedAliraqi\LaravelDeepLink\Http\Controllers;

use AhmedAliraqi\LaravelDeepLink\DeepLinkManager;
use Illuminate\Http\JsonResponse;

class VerificationController
{
    /** iOS Universal Links verification file. */
    public function apple(DeepLinkManager $deepLinks): JsonResponse
    {
        $payload = $deepLinks->appleAppSiteAssociation();

        abort_if($payload === null, 404);

        return response()->json($payload, options: JSON_UNESCAPED_SLASHES);
    }

    /** Android App Links verification file. */
    public function android(DeepLinkManager $deepLinks): JsonResponse
    {
        $payload = $deepLinks->assetLinks();

        abort_if($payload === null, 404);

        return response()->json($payload, options: JSON_UNESCAPED_SLASHES);
    }
}
