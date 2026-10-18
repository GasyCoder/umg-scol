import { useState } from 'react';

import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const months = [
    'janvier',
    'février',
    'mars',
    'avril',
    'mai',
    'juin',
    'juillet',
    'août',
    'septembre',
    'octobre',
    'novembre',
    'décembre',
];
const currentYear = new Date().getFullYear();
// Students are at least 14 years old; the most recent years come first.
const years = Array.from(
    { length: 70 },
    (_, index) => currentYear - 14 - index,
);

const daysIn = (month: string, year: string) =>
    month ? new Date(Number(year) || 2000, Number(month), 0).getDate() : 31;
const pad = (value: number) => String(value).padStart(2, '0');

/**
 * Birth date as three shadcn selects (day, month, year): quicker than a calendar on a phone,
 * where a birth date is twenty years back. Reports YYYY-MM-DD once complete, '' otherwise.
 */
export function BirthDateField({
    value,
    onChange,
    disabled,
}: {
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}) {
    // Back from the next step, the date already given is shown again.
    const [initialYear = '', initialMonth = '', initialDay = ''] = value
        ? value.split('-')
        : [];
    const [day, setDay] = useState(initialDay);
    const [month, setMonth] = useState(initialMonth);
    const [year, setYear] = useState(initialYear);

    function update(next: { day?: string; month?: string; year?: string }) {
        const parts = { day, month, year, ...next };
        // 31 then February, or 29 February then a common year: the day is chosen again.
        if (parts.day && Number(parts.day) > daysIn(parts.month, parts.year)) {
            parts.day = '';
        }
        setDay(parts.day);
        setMonth(parts.month);
        setYear(parts.year);
        onChange(
            parts.day && parts.month && parts.year
                ? `${parts.year}-${parts.month}-${parts.day}`
                : '',
        );
    }

    const trigger = 'w-full text-base data-[size=default]:h-12';

    return (
        <fieldset className="space-y-2" disabled={disabled}>
            <legend className="sr-only">Date de naissance</legend>
            <div className="grid grid-cols-[1fr_1.6fr_1.3fr] gap-2">
                <div className="space-y-1.5">
                    <Label
                        htmlFor="birth-day"
                        className="text-xs font-normal text-muted-foreground"
                    >
                        Jour
                    </Label>
                    <Select
                        value={day}
                        onValueChange={(value) => update({ day: value })}
                        disabled={disabled}
                    >
                        <SelectTrigger id="birth-day" className={trigger}>
                            <SelectValue placeholder="JJ" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {Array.from(
                                { length: daysIn(month, year) },
                                (_, index) => pad(index + 1),
                            ).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label
                        htmlFor="birth-month"
                        className="text-xs font-normal text-muted-foreground"
                    >
                        Mois
                    </Label>
                    <Select
                        value={month}
                        onValueChange={(value) => update({ month: value })}
                        disabled={disabled}
                    >
                        <SelectTrigger id="birth-month" className={trigger}>
                            <SelectValue placeholder="Mois" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {months.map((label, index) => (
                                <SelectItem key={label} value={pad(index + 1)}>
                                    {label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label
                        htmlFor="birth-year"
                        className="text-xs font-normal text-muted-foreground"
                    >
                        Année
                    </Label>
                    <Select
                        value={year}
                        onValueChange={(value) => update({ year: value })}
                        disabled={disabled}
                    >
                        <SelectTrigger id="birth-year" className={trigger}>
                            <SelectValue placeholder="AAAA" />
                        </SelectTrigger>
                        <SelectContent className="max-h-72">
                            {years.map((value) => (
                                <SelectItem key={value} value={String(value)}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </fieldset>
    );
}
