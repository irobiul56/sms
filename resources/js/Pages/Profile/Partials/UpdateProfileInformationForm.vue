<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    institute: {
        type: Object,
        required: true,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    mobile: user.mobile ?? '',
    username: user.username ?? '',
    institute_type: props.institute.type ?? '',
    eiin: props.institute.eiin ?? '',
    institute_name: props.institute.name ?? '',
    address: props.institute.address ?? '',
    logo: null,
});

const logoPreview = ref(props.institute.logo_url);

function selectLogo(event) {
    const file = event.target.files?.[0];
    form.logo = file ?? null;
    logoPreview.value = file ? URL.createObjectURL(file) : props.institute.logo_url;
}

function submit() {
    form.transform((data) => ({ ...data, _method: 'patch' })).post(route('profile.update'), {
        forceFormData: true,
        onSuccess: () => {
            form.logo = null;
        },
    });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                অ্যাকাউন্ট ও প্রতিষ্ঠানের তথ্য
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                আপনার অ্যাকাউন্ট, প্রতিষ্ঠানের তথ্য এবং লোগো পরিবর্তন করুন।
            </p>
        </header>

        <form
            @submit.prevent="submit"
            class="mt-6 space-y-6"
        >
            <h3 class="border-b border-gray-100 pb-2 text-sm font-semibold text-gray-800">প্রতিষ্ঠানের তথ্য</h3>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="institute_type" class="block text-sm font-medium text-gray-700">প্রতিষ্ঠানের ধরন *</label>
                    <select id="institute_type" v-model="form.institute_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option disabled value="">ধরন নির্বাচন করুন</option>
                        <option value="school">স্কুল</option>
                        <option value="college">কলেজ</option>
                        <option value="madrasa">মাদ্রাসা</option>
                        <option value="technical">কারিগরি প্রতিষ্ঠান</option>
                        <option value="university">বিশ্ববিদ্যালয়</option>
                        <option value="other">অন্যান্য</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.institute_type" />
                </div>
                <div>
                    <label for="eiin" class="block text-sm font-medium text-gray-700">EIIN নম্বর *</label>
                    <input id="eiin" v-model="form.eiin" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.eiin" />
                </div>
                <div class="sm:col-span-2">
                    <label for="institute_name" class="block text-sm font-medium text-gray-700">প্রতিষ্ঠানের নাম *</label>
                    <input id="institute_name" v-model="form.institute_name" type="text" maxlength="255" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.institute_name" />
                </div>
                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">ঠিকানা *</label>
                    <textarea id="address" v-model="form.address" rows="2" maxlength="1000" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    <InputError class="mt-1" :message="form.errors.address" />
                </div>
                <div class="sm:col-span-2">
                    <label for="logo" class="block text-sm font-medium text-gray-700">প্রতিষ্ঠানের লোগো</label>
                    <div class="mt-2 flex items-center gap-4">
                        <img v-if="logoPreview" :src="logoPreview" alt="প্রতিষ্ঠানের লোগো" class="h-16 w-16 rounded-lg border border-gray-200 object-contain p-1">
                        <input id="logo" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:font-medium file:text-indigo-700 hover:file:bg-indigo-100" @change="selectLogo">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG বা WEBP; সর্বোচ্চ ২ MB</p>
                    <InputError class="mt-1" :message="form.errors.logo" />
                </div>
            </div>

            <h3 class="border-b border-gray-100 pb-2 pt-2 text-sm font-semibold text-gray-800">আপনার অ্যাকাউন্ট</h3>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">পূর্ণ নাম *</label>
                    <input id="name" v-model="form.name" type="text" maxlength="255" required autocomplete="name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.name" />
                </div>
                <div>
                    <label for="mobile" class="block text-sm font-medium text-gray-700">মোবাইল *</label>
                    <input id="mobile" v-model="form.mobile" type="tel" maxlength="30" required autocomplete="tel" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.mobile" />
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">ইমেইল *</label>
                    <input id="email" v-model="form.email" type="email" maxlength="255" required autocomplete="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">ইউজারনেম *</label>
                    <input id="username" v-model="form.username" type="text" minlength="3" maxlength="30" pattern="[A-Za-z0-9_-]+" required autocomplete="username" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <InputError class="mt-1" :message="form.errors.username" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600"
                    >
                        Saved.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
