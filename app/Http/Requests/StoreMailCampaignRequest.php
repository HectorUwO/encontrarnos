<?php

namespace App\Http\Requests;

use App\Models\MailCampaign;
use App\Services\CampaignAudience;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'audience' => ['required', Rule::in(MailCampaign::AUDIENCES)],
            'custom_emails' => ['required_if:audience,custom', 'nullable', 'string', 'max:10000'],
            'subject' => ['required', 'string', 'max:150'],
            'heading' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:5000'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:40'],
            'button_url' => ['nullable', 'required_with:button_label', 'url:http,https', 'max:500'],
        ];
    }

    /**
     * @return list<string>
     */
    public function customEmails(): array
    {
        return app(CampaignAudience::class)->parseEmails($this->input('custom_emails'));
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->input('audience') !== 'custom') {
                    return;
                }

                $emails = $this->customEmails();

                if (count($emails) > 200) {
                    $validator->errors()->add('custom_emails', 'Puedes enviar a un máximo de 200 correos a la vez.');

                    return;
                }

                foreach ($emails as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $validator->errors()->add('custom_emails', "«{$email}» no es un correo válido.");

                        return;
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'custom_emails.required_if' => 'Escribe al menos un correo.',
            'button_label.required_with' => 'Escribe el texto del botón.',
            'button_url.required_with' => 'Escribe el enlace del botón.',
            'button_url.url' => 'El enlace debe empezar con http:// o https://.',
        ];
    }
}
