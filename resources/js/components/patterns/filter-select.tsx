import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

type Option = { value: string; label: string };

type Props = {
    id: string;
    label: string;
    value: string;
    allLabel: string;
    options: Option[];
    onValueChange: (value: string) => void;
    className?: string;
    'data-test'?: string;
};

/**
 * A labelled filter dropdown whose "all" choice uses the value `all`, the
 * sentinel every list page already sends for "no filter".
 */
export function FilterSelect({
    id,
    label,
    value,
    allLabel,
    options,
    onValueChange,
    className,
    ...props
}: Props) {
    const active = value !== 'all';

    return (
        <div className={cn('grid min-w-0 gap-1.5', className)}>
            <Label htmlFor={id} className="text-muted-foreground text-xs">
                {label}
            </Label>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger
                    id={id}
                    size="sm"
                    className={cn(
                        'w-full',
                        active &&
                            'border-primary/60 bg-primary/5 dark:bg-primary/10',
                    )}
                    {...props}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{allLabel}</SelectItem>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
