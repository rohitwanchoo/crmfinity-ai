<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'API authentication endpoints for token management'
)]
class AuthController extends Controller
{
    /**
     * Login and get API token.
     */
    #[OA\Post(
        path: '/auth/login',
        summary: 'Login and get API token',
        description: 'Authenticate with email and password to receive a Bearer token for API access.',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        example: 'user@example.com',
                        description: 'User email address'
                    ),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        format: 'password',
                        example: 'password123',
                        description: 'User password'
                    ),
                    new OA\Property(
                        property: 'device_name',
                        type: 'string',
                        example: 'API Client',
                        description: 'Name of the device/client requesting the token (optional)'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|abcdef123456...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                                        new OA\Property(property: 'full_name', type: 'string', example: 'John Doe')
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The provided credentials are incorrect.')
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            )
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        // Support both OAuth2 format (username) and regular format (email)
        $email = $request->input('email') ?? $request->input('username');
        $password = $request->input('password');
        $isOAuth2 = $request->has('grant_type') || $request->has('username');

        if (! $email || ! $password) {
            return response()->json([
                'success' => false,
                'message' => 'Email and password are required.',
            ], 422);
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($isOAuth2) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'The provided credentials are incorrect.',
                ], 401);
            }
            return response()->json([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        if (! $user->is_active) {
            if ($isOAuth2) {
                return response()->json([
                    'error' => 'invalid_grant',
                    'error_description' => 'Your account has been deactivated.',
                ], 401);
            }
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deactivated.',
            ], 401);
        }

        $deviceName = $request->device_name ?? 'API Client';
        $token = $user->createToken($deviceName, ['*'])->plainTextToken;

        // Update last login
        $user->update(['last_login' => now()]);

        // Return OAuth2 format for Swagger UI authorization
        if ($isOAuth2) {
            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => null,
            ]);
        }

        // Return standard API format
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'username' => $user->username,
                ],
            ],
        ]);
    }

    /**
     * Get current authenticated user.
     */
    #[OA\Get(
        path: '/auth/user',
        summary: 'Get current user',
        description: 'Get the currently authenticated user details.',
        tags: ['Authentication'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                                new OA\Property(property: 'full_name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'username', type: 'string', example: 'johndoe'),
                                new OA\Property(property: 'last_login', type: 'string', format: 'datetime')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
                    ]
                )
            )
        ]
    )]
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'full_name' => $user->full_name,
                'username' => $user->username,
                'last_login' => $user->last_login,
            ],
        ]);
    }

    /**
     * List all tokens for the current user.
     */
    #[OA\Get(
        path: '/auth/tokens',
        summary: 'List API tokens',
        description: 'Get a list of all active API tokens for the authenticated user.',
        tags: ['Authentication'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tokens retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'API Client'),
                                    new OA\Property(property: 'last_used_at', type: 'string', format: 'datetime', nullable: true),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'datetime')
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
                    ]
                )
            )
        ]
    )]
    public function tokens(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    /**
     * Revoke a specific token.
     */
    #[OA\Delete(
        path: '/auth/tokens/{tokenId}',
        summary: 'Revoke a token',
        description: 'Revoke a specific API token by its ID.',
        tags: ['Authentication'],
        security: [['sanctumAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'tokenId',
                in: 'path',
                required: true,
                description: 'Token ID to revoke',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token revoked successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Token revoked successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Token not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
                    ]
                )
            )
        ]
    )]
    public function revokeToken(Request $request, int $tokenId): JsonResponse
    {
        $token = $request->user()->tokens()->find($tokenId);

        if (! $token) {
            return response()->json([
                'success' => false,
                'error' => 'Token not found',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token revoked successfully',
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Logout',
        description: 'Revoke the current API token (logout).',
        tags: ['Authentication'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
                    ]
                )
            )
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices (revoke all tokens).
     */
    #[OA\Post(
        path: '/auth/logout-all',
        summary: 'Logout from all devices',
        description: 'Revoke all API tokens for the current user.',
        tags: ['Authentication'],
        security: [['sanctumAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged out from all devices',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out from all devices'),
                        new OA\Property(property: 'tokens_revoked', type: 'integer', example: 3)
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.')
                    ]
                )
            )
        ]
    )]
    public function logoutAll(Request $request): JsonResponse
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices',
            'tokens_revoked' => $count,
        ]);
    }
}
