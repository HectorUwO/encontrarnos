import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar contraseña" />
            <div className="en-auth-form-heading">
                <span>ACCESO A LA PLATAFORMA</span>
                <h2>Recuperar contraseña</h2>
                <p>
                    Escribe tu correo electrónico. Te enviaremos un enlace para
                    establecer una nueva contraseña.
                </p>
            </div>
            {status && (
                <div className="en-auth-status" role="status">
                    {status}
                </div>
            )}
            <form onSubmit={submit} className="en-auth-form">
                <div className="en-auth-field">
                    <label htmlFor="email">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        autoFocus
                        required
                        autoComplete="username"
                        placeholder="tu@correo.com"
                    />
                    {errors.email && (
                        <span className="en-auth-error">{errors.email}</span>
                    )}
                </div>
                <button
                    type="submit"
                    className="en-auth-submit"
                    disabled={processing}
                >
                    Enviar enlace <ArrowUpRight size={19} />
                </button>
            </form>
        </GuestLayout>
    );
}
