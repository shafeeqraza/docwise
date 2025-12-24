<?php

namespace App\Http\Controllers\V1\Api;

use App\Http\Controllers\V1\Concerns\ResponseHandler;
use App\Contracts\V1\SuperAdminLoginServiceInterface;
use App\Contracts\V1\SuperAdminLogOutServiceInterface;
use App\Exceptions\AuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminLoginRequest;
use App\Http\Resources\LoginResponseResource;
use App\Http\Resources\SuperAdminResource;
use App\Repositories\V1\AdminActionRepositoryInterface;
use App\Services\V1\Auth\AuthCookieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminAuthController extends Controller
{
    use ResponseHandler;

    /**
     * Create a new controller instance.
     *
     * @param SuperAdminLoginServiceInterface $superAdminLoginService
     * @param SuperAdminLogOutServiceInterface $superAdminLogOutService
     * @param AuthCookieService $authCookieService
     * @param AdminActionRepositoryInterface $adminActionRepository
     */
    public function __construct(
        private readonly SuperAdminLoginServiceInterface $superAdminLoginService,
        private readonly SuperAdminLogOutServiceInterface $superAdminLogOutService,
        private readonly AuthCookieService $authCookieService,
        private readonly AdminActionRepositoryInterface $adminActionRepository,

    ) {}

    /**
     * Handle superadmin login request.
     *
     * @param SuperAdminLoginRequest $request
     * @return JsonResponse
     */
    public function login(SuperAdminLoginRequest $request): JsonResponse
    {
        try {
            $loginData = $this->superAdminLoginService->login($request);
            $token = $loginData['token'] ?? '';

            $response = $this->respondResource(
                new LoginResponseResource($loginData),
                message: 'Login successful'
            );

            // Log admin action
            $this->adminActionRepository->logAction(
                user: $loginData['user'],
                action: 'auth.login',
                targetCompanyId: null,
                details: [
                    'email' => $loginData['user'],
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                request: $request
            );

            return $this->authCookieService->attachAuthCookie($response, $token);
        } catch (AuthenticationException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            return $this->respondError('An error occurred during login', 500);
        }
    }

    /**
     * Handle superadmin logout request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $this->superAdminLogOutService->logout(
                $user
            );

            // Log admin action before revoking token
            $this->adminActionRepository->logAction(
                user: $user,
                action: 'auth.logout',
                targetCompanyId: null,
                details: [
                    'email' => $user->email,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                request: $request
            );

            $response = $this->respondMessage('Logged out successfully', 200);
            return $this->authCookieService->clearAuthCookie($response);
        } catch (AuthenticationException $e) {
            return $e->render($request);
        } catch (\Exception $e) {
            return $this->respondError('An error occurred during logout', 500);
        }
    }

    /**
     * Get authenticated superadmin user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return $this->respondResource(new SuperAdminResource($user));
    }
}
