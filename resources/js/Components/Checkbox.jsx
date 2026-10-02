export default function Checkbox({ className = '', ...props }) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-neutral-600 dark:bg-neutral-800 dark:text-yellow-400 dark:focus:ring-yellow-400 dark:focus:ring-offset-neutral-900 ' +
                className
            }
        />
    );
}
