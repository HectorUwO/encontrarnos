import { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, Send } from 'lucide-react';
import { FormEvent } from 'react';

/**
 * «Tengo información»: envía un mensaje desde una ficha. Solo las cuentas con
 * el correo verificado pueden escribir, para evitar abusos.
 */
export default function InformationForm({
    action,
    heading = '¿Tienes información?',
    intro,
}: {
    action: string;
    heading?: string;
    intro: string;
}) {
    const { auth, flash } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors, reset, wasSuccessful } =
        useForm({ message: '', phone: '' });
    const user = auth?.user;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post(action, { preserveScroll: true, onSuccess: () => reset() });
    };

    return (
        <section className="en-info-form" aria-labelledby="info-form-title">
            <h3 id="info-form-title">{heading}</h3>
            <p>{intro}</p>
            {!user ? (
                <p className="en-info-form-gate">
                    <Link href={route('login')}>Inicia sesión</Link> o{' '}
                    <Link href={route('register')}>crea una cuenta</Link> para
                    compartir información.
                </p>
            ) : !user.email_verified_at ? (
                <p className="en-info-form-gate">
                    Verifica tu correo para poder compartir información.{' '}
                    <Link href={route('verification.notice')}>Verificar</Link>
                </p>
            ) : (
                <form onSubmit={submit}>
                    {wasSuccessful && flash?.status === 'information-sent' && (
                        <p className="en-demo-note" role="status">
                            <Check size={19} /> Enviamos tu mensaje. Gracias por
                            ayudar.
                        </p>
                    )}
                    <label>
                        Información que quieres compartir
                        <textarea
                            rows={5}
                            required
                            minLength={10}
                            maxLength={2000}
                            value={data.message}
                            onChange={(event) =>
                                setData('message', event.target.value)
                            }
                            placeholder="Dónde y cuándo la viste, con quién estaba, cualquier dato que ayude."
                        />
                        {errors.message && (
                            <span className="en-form-error" role="alert">
                                {errors.message}
                            </span>
                        )}
                    </label>
                    <label>
                        Teléfono <small>(opcional)</small>
                        <input
                            type="tel"
                            autoComplete="tel"
                            maxLength={30}
                            value={data.phone}
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                            placeholder="Para que puedan llamarte"
                        />
                        {errors.phone && (
                            <span className="en-form-error" role="alert">
                                {errors.phone}
                            </span>
                        )}
                    </label>
                    <p className="en-info-form-note">
                        Tu nombre y tu correo se comparten con quien recibe el
                        mensaje. No es un canal de emergencia: si hay un riesgo
                        inmediato, llama al 911.
                    </p>
                    <button
                        type="submit"
                        className="en-primary-button"
                        disabled={processing}
                        aria-busy={processing}
                    >
                        {processing ? 'Enviando…' : 'Enviar información'}{' '}
                        {processing ? (
                            <LoaderCircle
                                className="en-spin"
                                size={19}
                                aria-hidden="true"
                            />
                        ) : (
                            <Send size={19} />
                        )}
                    </button>
                </form>
            )}
        </section>
    );
}
