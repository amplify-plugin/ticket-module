<?php

namespace Amplify\System\Ticket\Requests;

use Amplify\System\Ticket\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;

class TicketRequest extends FormRequest
{
    public const ATTACHMENT_MIMES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,txt,rtf,csv,xls,xlsx,ppt,pptx';

    public static function acceptAttribute(): string
    {
        return '.'.str_replace(',', ',.', self::ATTACHMENT_MIMES);
    }

    /**
     * @return array<int, string>
     */
    public static function attachmentItemRules(): array
    {
        return ['file', 'mimes:'.self::ATTACHMENT_MIMES, 'max:10240'];
    }
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
            'message' => 'required_without:attachments|nullable|min:1',
            'attachments' => 'required_without:message|array',
            'attachments.*' => self::attachmentItemRules(),
        ];

        if (Route::is('tickets.store')) {
            $rules['subject'] = 'required';
            $rules['departments_name_id'] = 'required';
            $rules['priority'] = 'required|in:'.implode(',', Ticket::PRIORITY);
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
            'attachments.*.mimes' => 'Attach an image, PDF, Word, Excel, PowerPoint, CSV, or text file.',
        ];
    }
}
