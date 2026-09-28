import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar correo" />
            <div className="en-auth-form-heading">
                <span>VERIFICACIÓN DE CUENTA</span>
                <h2>Verifica tu correo</h2>
                <p>
                    Te enviamos un enlace de verificación. Ábrelo desde tu
                    correo electrónico para continuar.
                </p>
            </div>
            {status === 'verification-link-sent' && (
                <div className="en-auth-status" role="status">
                    Enviamos un nuevo enlace de verificación a tu correo.
                </div>
            )}
            <form onSubmit={submit} className="en-auth-form">
                <button
                    type="submit"
                    className="en-auth-submit"
                    disabled={processing}
                >
                    Reenviar enlace <ArrowUpRight size={19} />
                </button>
            </form>
            <p className="en-auth-switch">
                <Link href={route('logout')} method="post" as="button">
                    Cerrar sesión
                </Link>
            </p>
        </GuestLayout>
    );
}
