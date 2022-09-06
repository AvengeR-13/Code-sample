<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TicketHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetAccompanyingPeopleRequest;
use App\Http\Requests\LoginAccompanyingPerson;
use App\Http\Requests\SendCodeAccompanyingPerson;
use App\Http\Resources\AccompanyingPersonResource;
use App\Jobs\SendEmailJob;
use App\Mail\LoginCodeMail;
use App\Models\AccompanyingPerson;
use App\Notifications\GeneratedNewLoginCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccompanyingPersonController extends Controller
{
    /**
     * @param GetAccompanyingPeopleRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(GetAccompanyingPeopleRequest $request): AnonymousResourceCollection
    {
        return AccompanyingPersonResource::collection(AccompanyingPerson::all());
    }

    public function login(LoginAccompanyingPerson $request): JsonResponse
    {
        $person = AccompanyingPerson::where('person_email', $request->input('email'))->where('person_secret', $request->input('code'))->get();
        if ($person->count() === 1) {
            $person = $person->first();
            $person->update(['person_secret' => null]);
            return Response()->json([
                'status' => true,
                'userData' => [
                    'userClass' => AccompanyingPerson::class,
                    'user_id' => $person->person_id,
                    'user_name' => $person->person_name,
                    'user_surname' => $person->person_surname,
                    'user_photo' => null,
                    'user_title' => null,
                    'user_company' => null,
                    'user_email' => $person->person_email,
                    'roles' => [],
                    'rolesNames' => 'Accompanying Person',
                ],
                'ticket' => [
                    'encryptedCode' => (new TicketHelper())->generateQRContent($person->ticket->ticket_id),
                    'ticketId' => $person->ticket->ticket_id,
                    'ticket_type_id' => $person->ticket->ticketType->ticket_type_id,
                    'ticket_created_at' => $person->ticket->created_at->unix(),
                ]
            ]);
        }
        return Response()->json(['success' => false], 422);
    }

    /**
     * @param SendCodeAccompanyingPerson $request
     * @return JsonResponse
     */
    public function sendCode(SendCodeAccompanyingPerson $request): JsonResponse
    {
        $person = AccompanyingPerson::where('person_email', $request->input('email'))->get()->first();
        $person->update(['person_secret' => rand(10000000, 99999999)]);
        SendEmailJob::dispatch($person->person_email, LoginCodeMail::class, $person->person_name.' '.$person->person_surname, $person->person_secret)->onQueue('Mails');
        return Response()->json(['success' => true]);
    }
}
