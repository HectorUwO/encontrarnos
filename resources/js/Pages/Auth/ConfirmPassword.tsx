import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });
    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('password.confirm'), { onFinish: () => reset('password') });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar contraseña" />
            <div className="en-auth-form-heading">
                <span>ÁREA SEGURA</span>
                <h2>Confirma tu contraseña</h2>
                <p>Por seguridad, confirma tu contraseña antes de continuar.</p>
            </div>
            <form onSubmit={submit} className="en-auth-form">
                <div className="en-auth-field">
                    <label htmlFor="password">Contraseña</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                        autoComplete="current-password"
                        autoFocus
                        required
                    />
                    {errors.password && (
                        <span className="en-auth-error">{errors.password}</span>
                    )}
                </div>
                <button
                    type="submit"
                    className="en-auth-submit"
                    disabled={processing}
                >
                    Confirmar <ArrowUpRight size={19} />
                </button>
            </form>
        </GuestLayout>
    );
}
