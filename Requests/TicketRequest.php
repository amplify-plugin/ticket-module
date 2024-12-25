<?php

namespace Amplify\System\Ticket\Requests;

use Amplify\System\Ticket\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

class TicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'subject' => 'required|max:255',
            'departments_name_id' => 'required|integer',
            'priority' => 'required|in:'.implode(',', Ticket::PRIORITY),
            'message' => 'required_without:attachments|nullable|min:1',
            'attachments' => 'required_without:message|array',
            'attachments.*' => 'file',
        ];

        if (Route::is('tickets.store')) {
            $rules['subject'] = 'required';
            $rules['departments_name_id'] = 'required';
            $rules['priority'] = 'required';
        }

        return $rules;
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'attachments.*.mimetypes' => 'Every attachment should be a valid file.',
        ];
    }
}
