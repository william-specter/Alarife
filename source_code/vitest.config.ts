import { defineConfig } from 'vitest/config';
import path from 'path';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
