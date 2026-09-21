import { useHttp, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label as FormLabel } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { store, update } from '@/routes/setup/labels';
import type { Label } from '@/types';

export type LabelFormData = {
    name: string;
    color: string;
    description: string;
};

export type LabelSavedResponse = {
    label: Label;
    message: string;
};

type Props = {
    label?: Label | null;
    onSaved?: (label: Label, message: string) => void;
    onCancel?: () => void;
};

export default function LabelForm({ label = null, onSaved, onCancel }: Props) {
    const teamSlug = usePage().props.currentTeam?.slug;
    const form = useHttp<LabelFormData, LabelSavedResponse>(
        () =>
            label
                ? update.put([teamSlug ?? '', label.id])
                : store(teamSlug ?? ''),
        {
            name: label?.name ?? '',
            color: label?.color ?? '',
            description: label?.description ?? '',
        },
    );

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        void form.submit({
            onSuccess: (response) => {
                onSaved?.(response.label, response.message);
            },
        });
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-2">
                <FormLabel htmlFor="label-name">Name</FormLabel>
                <Input
                    id="label-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    placeholder="Bug"
                    required
                    data-test="label-name"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <FormLabel htmlFor="label-color">Color</FormLabel>
                <div className="flex items-center gap-2">
                    <Input
                        id="label-color"
                        type="color"
                        className="h-9 w-12 p-1"
                        value={form.data.color || '#6b7280'}
                        onChange={(event) =>
                            form.setData('color', event.target.value)
                        }
                        data-test="label-color"
                    />
                    <Input
                        value={form.data.color}
                        onChange={(event) =>
                            form.setData('color', event.target.value)
                        }
                        placeholder="#6b7280"
                        className="flex-1"
                    />
                </div>
                <InputError message={form.errors.color} />
            </div>

            <div className="grid gap-2">
                <FormLabel htmlFor="label-description">Description</FormLabel>
                <Textarea
                    id="label-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    data-test="label-description"
                />
                <InputError message={form.errors.description} />
            </div>

            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                {onCancel ? (
                    <Button type="button" variant="secondary" onClick={onCancel}>
                        Cancel
                    </Button>
                ) : null}

                <Button
                    type="submit"
                    disabled={form.processing || !teamSlug}
                    data-test="label-submit"
                >
                    {label ? 'Save changes' : 'Create label'}
                </Button>
            </div>
        </form>
    );
}
