<?php

namespace App\Domains\Shared\Traits;

use App\Domains\Shared\Exceptions\BaseControllerException;
use Illuminate\Http\Request;

trait ResolvesFormRequest
{
    /**
     * @return \Illuminate\Foundation\Http\FormRequest
     */
    protected function request(Request $request)
    {
        $createRequest = $this->getRequest();

        if (empty($createRequest)) {
            throw new BaseControllerException('Você precisa fornecer um FormRequest nas dependências.', -1);
        }

        $class = $createRequest['requestClass'];
        /** @var \Illuminate\Foundation\Http\FormRequest $newRequest */
        $newRequest = $class::createFrom($request);
        $newRequest->setContainer(app());
        $newRequest->setRedirector(app('redirect'));
        $newRequest->setUserResolver($request->getUserResolver());
        $newRequest->setRouteResolver($request->getRouteResolver());
        $newRequest->validateResolved();

        return $newRequest;
    }
}
