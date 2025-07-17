<?php

namespace App\Domains\Shared\Controller;

use App\Domains\Shared\Interfaces\IController;
use App\Domains\Shared\Traits\Dependencies;
use App\Domains\Shared\Traits\HasACL;
use App\Http\Controllers\Controller;
use App\Domains\Shared\Exceptions\BaseControllerException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Class BaseController
 *
 * This class extends the base Controller class and implements the IController interface.
 * It uses the Dependencies trait and provides methods for handling HTTP requests.
 */
class BaseController extends Controller implements IController
{
    use Dependencies, HasACL;

    /**
     * BaseController constructor.
     *
     * Applies the 'auth:api' middleware to all routes except 'login', 'register', 'forgotPassword', and 'resetPassword'.
     *
     * @throws Exception
     */
    public function __construct()
    {
        $this->bootACL();
    }

    /**
     * Handles the incoming request and validates it using the rules defined in the FormRequest.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return Request The validated request.
     *
     * @throws BaseControllerException If no FormRequest is provided in the dependencies.
     */
    protected function request(Request $request)
    {
        $createRequest = $this->getRequest();

        if (empty($createRequest)) {
            throw new BaseControllerException('Você precisa fornecer um FormRequest nas dependências.', -1);
        }

        $newRequest = $createRequest['requestClass']::createFrom($request);

        $newRequest->validate($newRequest->rules());

        return $newRequest;
    }

    /**
     * Handles a GET request to list all resources.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return JsonResponse The list of resources in a JSON response.
     */
    public function index(Request $request, ?\Closure $builderCallback = null)
    {
        $options = $request->all();

        return response()->json($this->getService()->index($options, $builderCallback));
    }

    /**
     * Handles a POST request to create a new resource.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @return JsonResponse The created resource in a JSON response.
     *
     * @throws BaseControllerException If the request validation fails.
     * @throws Throwable If an error occurs during the creation of the resource.
     */
    public function store(Request $request)
    {
        try {
            $request = $this->request($request);

            return response()->json($this->getService()->store($request->all()));
        } catch (Throwable $th) {
            throw $th;
        }
    }

    /**
     * Handles a GET request to display a specific resource.
     *
     * @param  string  $id  The ID of the resource to display.
     * @return JsonResponse The specified resource in a JSON response.
     */
    public function show(string $id)
    {
        return response()->json($this->getService()->show($id));
    }

    /**
     * Handles a PUT or PATCH request to update a specific resource.
     *
     * @param  Request  $request  The incoming HTTP request.
     * @param  string  $id  The ID of the resource to update.
     * @return JsonResponse The updated resource in a JSON response.
     *
     * @throws BaseControllerException If the request validation fails.
     * @throws Throwable If an error occurs during the update of the resource.
     */
    public function update(Request $request, string $id)
    {
        try {
            $request = $this->request($request);

            return response()->json($this->getService()->update($request->all(), $id));
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function patch(Request $request, string $id)
    {
        try {
            $request = $this->request($request);

            return response()->json($this->getService()->patch($request->all(), $id));
        } catch (Throwable $th) {
            throw $th;
        }
    }

    /**
     * Handles a DELETE request to remove a specific resource.
     *
     * @param  string  $id  The ID of the resource to remove.
     * @return bool The status of the operation in a JSON response.
     */
    public function destroy(string $id)
    {
        return $this->getService()->destroy($id);
    }

    /**
     * Handles a GET request to search for a specific resource based on the provided field and value.
     * It can also handle searching within a related resource if the relation is provided.
     *
     * @param  Request  $request  The incoming HTTP request. The request data is used as options for the search.
     * @return JsonResponse Returns a JSON response containing the search results.
     */
    public function search(Request $request, ?\Closure $builderCallback = null)
    {
        $options = $request->all();

        return response()->json($this->getService()->search($options, $builderCallback));
    }
}
