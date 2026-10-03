import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import { Eye, Send } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Campaign = {
    id: number;
    subject: string;
    audience: string;
    recipients: number;
    sent: number;
    failed: number;
    sender: string | null;
    created_at: string | null;
};

const audienceLabels: Record<string, string> = {
    verified: 'Personas con correo verificado',
    all: 'Todas las cuentas',
    admins: 'Administradores',
    custom: 'Correos específicos',
};

const messages: Record<string, string> = {
    'campaign-queued':
        'Programamos el envío. Los correos salen en los próximos minutos.',
    'test-sent': 'Te enviamos una prueba a tu correo.',
};

export default function AdminMail({
    counts,
    campaigns,
    flash,
}: PageProps<{
    counts: { verified: number; all: number; admins: number };
    campaigns: Campaign[];
}>) {
    const { data, setData, post, processing, errors, reset } = useForm({
        audience: 'verified',
        custom_emails: '',
        subject: '',
        heading: '',
        body: '',
        button_label: '',
        button_url: '',
    });
    const [previewHtml, setPreviewHtml] = useState<string | null>(null);
    const [previewError, setPreviewError] = useState('');
    const [previewing, setPreviewing] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const status = flash?.status ? messages[flash.status] : undefined;

    const customCount = data.custom_emails
        .split(/[\s,;]+/)
        .filter(Boolean).length;
    const recipientCount =
        data.audience === 'custom'
            ? customCount
            : counts[data.audience as 'verified' | 'all' | 'admins'];

    const preview = async () => {
        setPreviewing(true);
        setPreviewError('');
        try {
            const response = await axios.post(
                route('admin.mail.preview'),
                data,
            );
            setPreviewHtml(response.data.html);
        } catch (error) {
            const messagesMap = axios.isAxiosError(error)
                ? (error.response?.data?.errors as
                      Record<string, string[]> | undefined)
                : undefined;
            setPreviewError(
                messagesMap
                    ? Object.values(messagesMap)[0][0]
                    : 'No pudimos generar la vista previa.',
            );
        } finally {
            setPreviewing(false);
        }
    };

    const sendTest = () =>
        post(route('admin.mail.test'), { preserveScroll: true });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!confirming) {
            setConfirming(true);
            return;
        }
        post(route('admin.mail.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setPreviewHtml(null);
                setConfirming(false);
            },
            onError: () => setConfirming(false),
        });
    };

    const field = (key: keyof typeof data) => ({
        id: key,
        value: data[key],
        onChange: (
            event: React.ChangeEvent<
                HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
            >,
        ) => {
            setData(key, event.target.value);
            setConfirming(false);
        },
    });

    return (
        <WorkspaceLayout active="admin-mail" crumb="ADMINISTRACIÓN / CORREOS">
            <Head title="Enviar correos" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">ADMINISTRACIÓN</span>
                        <h1>ENVIAR CORREOS.</h1>
                        <p>
                            Escribe un comunicado y se enviará con la misma
                            plantilla negra de los correos de la plataforma.
                        </p>
                    </div>
                </div>
                {status && (
                    <div
                        className="en-profile-status"
                        role="status"
                        style={{ marginTop: 24 }}
                    >
                        {status}
                    </div>
                )}
                <div className="en-mail-layout">
                    <form
                        className="en-profile-card"
                        onSubmit={submit}
                        noValidate
                    >
                        <div className="en-profile-form">
                            <div className="en-profile-field">
                                <label htmlFor="audience">Destinatarios</label>
                                <select {...field('audience')}>
                                    {(
                                        ['verified', 'all', 'admins'] as const
                                    ).map((key) => (
                                        <option key={key} value={key}>
                                            {audienceLabels[key]} ({counts[key]}
                                            )
                                        </option>
                                    ))}
                                    <option value="custom">
                                        {audienceLabels.custom}
                                    </option>
                                </select>
                                {errors.audience && (
                                    <span className="en-profile-error">
                                        {errors.audience}
                                    </span>
                                )}
                            </div>
                            {data.audience === 'custom' && (
                                <div className="en-profile-field">
                                    <label htmlFor="custom_emails">
                                        Correos (separados por coma o salto de
                                        línea)
                                    </label>
                                    <textarea
                                        {...field('custom_emails')}
                                        rows={4}
                                        placeholder="ana@correo.com, luis@correo.com"
                                    />
                                    {errors.custom_emails && (
                                        <span className="en-profile-error">
                                            {errors.custom_emails}
                                        </span>
                                    )}
                                </div>
                            )}
                            <div className="en-profile-field">
                                <label htmlFor="subject">Asunto</label>
                                <input {...field('subject')} maxLength={150} />
                                {errors.subject && (
                                    <span className="en-profile-error">
                                        {errors.subject}
                                    </span>
                                )}
                            </div>
                            <div className="en-profile-field">
                                <label htmlFor="heading">
                                    Título del correo
                                </label>
                                <input
                                    {...field('heading')}
                                    maxLength={80}
                                    placeholder="Novedades de la plataforma"
                                />
                                {errors.heading && (
                                    <span className="en-profile-error">
                                        {errors.heading}
                                    </span>
                                )}
                            </div>
                            <div className="en-profile-field">
                                <label htmlFor="body">Mensaje</label>
                                <textarea
                                    {...field('body')}
                                    rows={8}
                                    maxLength={5000}
                                />
                                <span className="en-profile-hint">
                                    Deja una línea en blanco para separar
                                    párrafos.
                                </span>
                                {errors.body && (
                                    <span className="en-profile-error">
                                        {errors.body}
                                    </span>
                                )}
                            </div>
                            <div className="en-profile-field">
                                <label htmlFor="button_label">
                                    Botón (opcional)
                                </label>
                                <input
                                    {...field('button_label')}
                                    maxLength={40}
                                    placeholder="Texto del botón"
                                />
                                {errors.button_label && (
                                    <span className="en-profile-error">
                                        {errors.button_label}
                                    </span>
                                )}
                                <input
                                    {...field('button_url')}
                                    id="button_url"
                                    type="url"
                                    placeholder="https://..."
                                    aria-label="Enlace del botón"
                                />
                                {errors.button_url && (
                                    <span className="en-profile-error">
                                        {errors.button_url}
                                    </span>
                                )}
                            </div>
                            {confirming && (
                                <div className="en-mail-confirm" role="alert">
                                    <strong>
                                        Se enviará a{' '}
                                        {recipientCount.toLocaleString('es-MX')}{' '}
                                        {recipientCount === 1
                                            ? 'persona'
                                            : 'personas'}
                                        .
                                    </strong>
                                    Esta acción no se puede deshacer. Confirma
                                    para enviar.
                                </div>
                            )}
                            <div className="en-profile-actions">
                                <button
                                    type="submit"
                                    className={`en-profile-button ${confirming ? 'en-profile-button-danger' : ''}`}
                                    disabled={processing}
                                >
                                    <Send
                                        size={14}
                                        style={{ marginRight: 8 }}
                                    />
                                    {confirming
                                        ? 'Confirmar y enviar'
                                        : 'Enviar campaña'}
                                </button>
                                <button
                                    type="button"
                                    className="en-profile-button en-profile-button-ghost"
                                    onClick={preview}
                                    disabled={previewing}
                                >
                                    <Eye size={14} style={{ marginRight: 8 }} />
                                    Vista previa
                                </button>
                                <button
                                    type="button"
                                    className="en-profile-button en-profile-button-ghost"
                                    onClick={sendTest}
                                    disabled={processing}
                                >
                                    Enviarme una prueba
                                </button>
                                {confirming && (
                                    <button
                                        type="button"
                                        className="en-profile-button en-profile-button-ghost"
                                        onClick={() => setConfirming(false)}
                                    >
                                        Cancelar
                                    </button>
                                )}
                            </div>
                        </div>
                    </form>
                    <aside className="en-profile-card en-mail-preview">
                        <span className="en-work-kicker">VISTA PREVIA</span>
                        {previewError && (
                            <div
                                className="en-profile-error"
                                style={{ marginTop: 12 }}
                            >
                                {previewError}
                            </div>
                        )}
                        {previewHtml ? (
                            <iframe
                                title="Vista previa del correo"
                                srcDoc={previewHtml}
                                sandbox=""
                            />
                        ) : (
                            <div className="en-mail-preview-empty">
                                Completa el asunto, el título y el mensaje, y
                                pulsa «Vista previa» para ver cómo se verá el
                                correo.
                            </div>
                        )}
                    </aside>
                </div>
                <section style={{ marginTop: 44 }}>
                    <span className="en-work-kicker">ENVÍOS RECIENTES</span>
                    <div className="en-admin-table-scroll">
                        <table className="en-admin-table">
                            <thead>
                                <tr>
                                    <th>ASUNTO</th>
                                    <th>DESTINATARIOS</th>
                                    <th>ENVIADOS</th>
                                    <th>FALLIDOS</th>
                                    <th>FECHA</th>
                                </tr>
                            </thead>
                            <tbody>
                                {campaigns.map((campaign) => (
                                    <tr key={campaign.id}>
                                        <td>
                                            {campaign.subject}
                                            <small>
                                                {audienceLabels[
                                                    campaign.audience
                                                ] ?? campaign.audience}
                                                {campaign.sender &&
                                                    ` · ${campaign.sender}`}
                                            </small>
                                        </td>
                                        <td>{campaign.recipients}</td>
                                        <td>{campaign.sent}</td>
                                        <td>{campaign.failed}</td>
                                        <td>{campaign.created_at}</td>
                                    </tr>
                                ))}
                                {campaigns.length === 0 && (
                                    <tr>
                                        <td colSpan={5}>
                                            Todavía no has enviado correos.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </WorkspaceLayout>
    );
}
