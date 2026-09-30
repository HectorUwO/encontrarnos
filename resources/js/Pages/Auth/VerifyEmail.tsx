import { ActionIcon } from '@/Components/Encontrarnos/motion';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { auth } = usePage<PageProps>().props;
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
                    Te enviamos un enlace de verificación a{' '}
                    <strong>{auth.user.email}</strong>. Ábrelo desde tu correo
                    electrónico para activar tu cuenta.
                </p>
            </div>
            {status === 'email-changed' && (
                <div className="en-auth-status" role="status">
                    Cambiaste tu correo. Enviamos un enlace a la nueva dirección
                    para que la verifiques.
                </div>
            )}
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
                    aria-busy={processing}
                >
                    Reenviar enlace <ActionIcon pending={processing} />
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
