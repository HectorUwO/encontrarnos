import { lightBackgroundSymbol } from '@/brand';
import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img {...props} src={lightBackgroundSymbol} alt="Encontrarnos" />;
}
