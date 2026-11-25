<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const instituteTypes = [
    { value: 'school', label: 'স্কুল' },
    { value: 'college', label: 'কলেজ' },
    { value: 'madrasa', label: 'মাদ্রাসা' },
    { value: 'technical', label: 'কারিগরি প্রতিষ্ঠান' },
    { value: 'university', label: 'বিশ্ববিদ্যালয়' },
    { value: 'other', label: 'অন্যান্য' },
];

const form = useForm({
    institute_type: '',
    eiin: '',
    institute_name: '',
    address: '',
    name: '',
    mobile: '',
    email: '',
    username: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout wide>
        <Head title="প্রতিষ্ঠান নিবন্ধন" />

        <div class="mb-8">
            <p class="text-sm font-semibold text-indigo-600">QUESTION MAKER</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900 sm:text-3xl">প্রতিষ্ঠান নিবন্ধন</h1>
            <p class="mt-2 text-sm text-gray-600">আপনার প্রতিষ্ঠানের তথ্য ও প্রশাসক অ্যাকাউন্ট তৈরি করুন। (*) চিহ্নিত তথ্যগুলো আবশ্যক।</p>
        </div>

        <form @submit.prevent="submit" class="space-y-7">
            <section class="rounded-2xl border border-gray-200 p-5 sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">প্রতিষ্ঠানের তথ্য</h2>
                    <p class="mt-1 text-sm text-gray-500">প্রতিষ্ঠানের পরিচিতি ও যোগাযোগের ঠিকানা</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="institute_type" class="block text-sm font-medium text-gray-700">প্রতিষ্ঠানের ধরন <span class="text-red-600">*</span></label>
                        <select id="institute_type" v-model="form.institute_type" required class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option disabled value="">ধরন নির্বাচন করুন</option>
                            <option v-for="type in instituteTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.institute_type" />
                    </div>

                    <div>
                        <label for="eiin" class="block text-sm font-medium text-gray-700">EIIN নম্বর <span class="text-red-600">*</span></label>
                        <input id="eiin" v-model="form.eiin" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="off" placeholder="৬ সংখ্যার EIIN" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.eiin" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="institute_name" class="block text-sm font-medium text-gray-700">প্রতিষ্ঠানের নাম <span class="text-red-600">*</span></label>
                        <input id="institute_name" v-model="form.institute_name" type="text" maxlength="255" required autocomplete="organization" placeholder="প্রতিষ্ঠানের পূর্ণ নাম" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.institute_name" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700">ঠিকানা <span class="text-red-600">*</span></label>
                        <textarea id="address" v-model="form.address" rows="3" maxlength="1000" required autocomplete="street-address" placeholder="গ্রাম/সড়ক, উপজেলা, জেলা" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                        <InputError class="mt-1" :message="form.errors.address" />
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 p-5 sm:p-6">
                <div class="mb-5">
                    <h2 class="text-lg font-semibold text-gray-900">আপনার অ্যাকাউন্ট</h2>
                    <p class="mt-1 text-sm text-gray-500">এই তথ্য দিয়ে আপনি প্রতিষ্ঠানের অ্যাডমিন হিসেবে প্রবেশ করবেন</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">পূর্ণ নাম <span class="text-red-600">*</span></label>
                        <input id="name" v-model="form.name" type="text" maxlength="255" required autofocus autocomplete="name" placeholder="আপনার পূর্ণ নাম" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.name" />
                    </div>

                    <div>
                        <label for="mobile" class="block text-sm font-medium text-gray-700">মোবাইল <span class="text-red-600">*</span></label>
                        <input id="mobile" v-model="form.mobile" type="tel" maxlength="30" required autocomplete="tel" placeholder="01XXXXXXXXX" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.mobile" />
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">ইমেইল <span class="text-red-600">*</span></label>
                        <input id="email" v-model="form.email" type="email" maxlength="255" required autocomplete="email" placeholder="name@example.com" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.email" />
                    </div>

                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700">ইউজারনেম <span class="text-red-600">*</span></label>
                        <input id="username" v-model="form.username" type="text" minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" required autocomplete="username" placeholder="ইংরেজি অক্ষর, সংখ্যা, _ বা -" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.username" />
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">পাসওয়ার্ড <span class="text-red-600">*</span></label>
                        <input id="password" v-model="form.password" type="password" required autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">পাসওয়ার্ড নিশ্চিত করুন <span class="text-red-600">*</span></label>
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" required autocomplete="new-password" class="mt-1.5 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <InputError class="mt-1" :message="form.errors.password_confirmation" />
                    </div>
                </div>
            </section>

            <div class="flex flex-col-reverse items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-gray-600">আগে থেকেই অ্যাকাউন্ট আছে?
                    <Link :href="route('login')" class="font-semibold text-indigo-600 hover:text-indigo-500">লগইন করুন</Link>
                </p>
                <PrimaryButton class="w-full justify-center px-6 py-3 sm:w-auto" :class="{ 'opacity-60': form.processing }" :disabled="form.processing">
                    {{ form.processing ? 'অ্যাকাউন্ট তৈরি হচ্ছে...' : 'প্রতিষ্ঠান নিবন্ধন করুন' }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
