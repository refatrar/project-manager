import { router } from '@inertiajs/react';
import { type ChangeEvent, useRef, useState } from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import InputError from '@/components/input-error';
import { useInitials } from '@/hooks/use-initials';
import {
    destroy as destroyAvatar,
    update as updateAvatar,
} from '@/routes/profile/avatar';

type Props = {
    name: string;
    avatar: string | null;
};

export default function AvatarUpload({ name, avatar }: Props) {
    const getInitials = useInitials();
    const inputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | undefined>(undefined);

    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        const formData = new FormData();
        formData.append('avatar', file);

        router.post(updateAvatar().url, formData, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => {
                setProcessing(true);
                setError(undefined);
            },
            onError: (errors) => setError(errors.avatar),
            onFinish: () => setProcessing(false),
        });
    };

    const removeAvatar = () => {
        router.delete(destroyAvatar().url, { preserveScroll: true });
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center gap-4">
                <Avatar className="size-16">
                    {avatar ? <AvatarImage src={avatar} alt={name} /> : null}
                    <AvatarFallback className="text-lg">
                        {getInitials(name)}
                    </AvatarFallback>
                </Avatar>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={processing}
                        onClick={() => inputRef.current?.click()}
                        data-test="avatar-change-button"
                    >
                        Change photo
                    </Button>
                    {avatar ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            disabled={processing}
                            onClick={removeAvatar}
                            data-test="avatar-remove-button"
                        >
                            Remove
                        </Button>
                    ) : null}
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/gif"
                        className="hidden"
                        onChange={handleFileChange}
                        data-test="avatar-file-input"
                    />
                </div>
            </div>
            <InputError message={error} />
        </div>
    );
}
