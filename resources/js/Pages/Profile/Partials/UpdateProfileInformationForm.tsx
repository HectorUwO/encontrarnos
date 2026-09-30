import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function UpdateProfileInformation({
    status,
}: {
    status?: string;
}) {
    const user = usePage<PageProps>().props.auth.user;

    const { data, setData, patch, errors, processing } = useForm({
        name: user.name,
        email: user.email,
    });

    const emailChanged = data.email.trim().toLowerCase() !== user.email;

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <section className="en-profile-card">
            <header>
                <span className="en-work-kicker">INFORMACIÓN PERSONAL</span>
                <h2>Nombre y correo</h2>
                <p>
                    Estos datos identifican tu cuenta. Tu correo está{' '}
                    <strong className="en-profile-verified">
                        <BadgeCheck size={15} /> verificado
                    </strong>
                    .
                </p>
            </header>
            {status === 'profile-updated' && (
                <div className="en-profile-status" role="status">
                    Guardamos tus cambios.
                </div>
            )}
            <form onSubmit={submit} className="en-profile-form">
                <div className="en-profile-field">
                    <label htmlFor="name">Nombre completo</label>
                    <input
                        id="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        required
                        autoComplete="name"
                    />
                    {errors.name && (
                        <span className="en-profile-error">{errors.name}</span>
                    )}
                </div>
                <div className="en-profile-field">
                    <label htmlFor="email">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        required
                        autoComplete="username"
                    />
                    {errors.email && (
                        <span className="en-profile-error">{errors.email}</span>
                    )}
                    {emailChanged && (
                        <span className="en-profile-hint">
                            Si cambias tu correo, te enviaremos un enlace para
                            verificarlo y deberás confirmarlo para seguir usando
                            tu cuenta.
                        </span>
                    )}
                </div>
                <div className="en-profile-actions">
                    <button
                        type="submit"
                        className="en-profile-button"
                        disabled={processing}
                    >
                        Guardar cambios
                    </button>
                </div>
            </form>
        </section>
    );
}
