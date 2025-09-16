<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Hyperf\Odin\Exception\LLMException\Api\LLMInvalidRequestException;
use Hyperf\Odin\Exception\LLMException\ErrorMappingManager;

require_once __DIR__ . '/../../vendor/autoload.php';

// Mock error response
$errorResponseBody = [
    'error' => [
        'code' => 'InvalidParameter.OversizeImage',
        'message' => 'The request failed because the size of the input image (222 MB) exceeds the limit (10 MB). Request id: mock-request-id-12345',
        'param' => 'image_url',
        'type' => 'BadRequest',
    ],
];

$httpResponse = new Response(400, [], json_encode($errorResponseBody));
$httpRequest = new Request('POST', 'https://api.example-llm-provider.com/v3/chat/completions');
$requestException = new RequestException('Invalid parameter: image_url', $httpRequest, $httpResponse);

try {
    $errorMappingManager = new ErrorMappingManager();
    $llmException = $errorMappingManager->mapException($requestException);

    if ($llmException instanceof LLMInvalidRequestException) {
        echo "✅ Test PASSED - Exception correctly mapped\n";
        echo 'Error Message: ' . $llmException->getMessage() . "\n\n";

        // Verify provider details are preserved
        $providerDetails = $llmException->getProviderErrorDetails();
        if ($providerDetails && isset($providerDetails['code']) && $providerDetails['code'] === 'InvalidParameter.OversizeImage') {
            echo "✅ Test PASSED - Provider error details preserved\n";
            echo 'Error Code: ' . $providerDetails['code'] . "\n";
            echo 'Error Type: ' . $providerDetails['type'] . "\n";
            echo 'Error Param: ' . $providerDetails['param'] . "\n";
        } else {
            echo "❌ Test FAILED - Provider error details missing or incomplete\n";
        }
    } else {
        echo '❌ Test FAILED - Wrong exception type: ' . get_class($llmException) . "\n";
    }
} catch (Exception $e) {
    echo '❌ Test FAILED - Exception during processing: ' . $e->getMessage() . "\n";
}
