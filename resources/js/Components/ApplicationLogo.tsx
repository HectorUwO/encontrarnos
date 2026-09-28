import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return <img {...props} src="/2.png" alt="Encontrarnos" />;
}
