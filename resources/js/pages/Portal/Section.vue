<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps({
    kind: String,
    section: String,
    profile: Object,
    data: { type: [Object, Array], required: true },
    children: Array,
});
const labels: any = {
    formation: 'Ma formation',
    planning: 'Mon planning',
    attendance: 'Mes présences',
    payments: 'Mes paiements',
    documents: 'Mes documents',
    notifications: 'Notifications',
    groups: 'Mes groupes',
    students: 'Mes étudiants',
};
const sessions = computed(() =>
    props.kind === 'student'
        ? ((props.data as any).sessions ?? [])
        : ((props.data as any).sessions ?? []),
);
const selected = ref<any>(null);
const records = useForm({ records: [] as any[] });
function choose(s: any) {
    selected.value = s;
    const enrollments = (props.data as any).students ?? [];
    records.records = enrollments
        .filter(
            (e: any) =>
                e.training_plan_group_id === s.training_plan_group_id,
        )
        .map((e: any) => ({
            student_id: e.student.id,
            student_name: `${e.student.first_name} ${e.student.last_name}`,
            photo_url: e.student.photo_url,
            status: s.attendances?.find((a: any) => a.student_id === e.student.id)?.status ?? 'present',
            notes: '',
        }));
}
function saveAttendance() {
    records.put(`/portal/sessions/${selected.value.id}/attendance`);
}
function read(n: any) {
    if (!n.read_at) router.patch(`/portal/notifications/${n.id}/read`);
}
const money = (v: any) => `${Number(v || 0).toLocaleString('fr-DZ')} DA`;
</script>
<template>
    <Head :title="labels[section]" /><AdminLayout
        ><main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-5">
                <h1 class="text-2xl font-semibold">{{ labels[section] }}</h1>
                <template v-if="kind === 'parent'"
                    ><section
                        v-if="section === 'notifications'"
                        class="space-y-2"
                    >
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p>{{ n.message }}</p>
                        </button>
                    </section>
                    <article
                        v-for="child in section === 'notifications'
                            ? []
                            : children"
                        :key="child.id"
                        class="rounded-xl border bg-card p-5"
                    >
                        <h2 class="font-semibold">
                            {{ child.first_name }} {{ child.last_name }}
                        </h2>
                        <div v-if="section === 'payments'" class="mt-3">
                            <p v-for="e in child.enrollments" :key="e.id">
                                {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }} — reste
                                {{ money(e.remaining_balance) }}
                            </p>
                        </div>
                        <div v-else-if="section === 'attendance'" class="mt-3">
                            <p v-for="a in child.attendances" :key="a.id">
                                {{ a.session?.title }} — {{ a.status }}
                            </p>
                        </div>
                        <div v-else-if="section === 'planning'" class="mt-3">
                            <p v-for="s in child.portal_sessions" :key="s.id">
                                {{ s.title }} · {{ s.starts_at }} ·
                                {{ s.classroom?.name }}
                            </p>
                        </div>
                        <div v-else class="mt-3">
                            <p v-for="e in child.enrollments" :key="e.id">
                                {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }} ·
                                {{ e.level || '—' }} · Groupe
                                {{ e.group_number || '—' }}
                            </p>
                        </div>
                    </article></template
                >
                <template v-else-if="kind === 'student'"
                    ><section v-if="section === 'formation'" class="space-y-3">
                        <article
                            v-for="e in data.enrollments"
                            :key="e.id"
                            class="rounded-xl border bg-card p-5"
                        >
                            <h2 class="font-semibold">
                                {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }}
                            </h2>
                            <p>
                                Niveau {{ e.level || '—' }} · Groupe
                                {{ e.group_number || '—' }}
                            </p>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'planning'"
                        class="space-y-3"
                    >
                        <article
                            v-for="s in data.sessions"
                            :key="s.id"
                            class="rounded-xl border bg-card p-4"
                        >
                            <b>{{ s.title }}</b>
                            <p class="text-sm text-muted-foreground">
                                {{ s.starts_at }} → {{ s.ends_at }} ·
                                {{ s.classroom?.name }}
                            </p>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'attendance'"
                        class="space-y-2"
                    >
                        <p
                            v-for="a in profile.attendances"
                            :key="a.id"
                            class="rounded-lg border p-3"
                        >
                            {{ a.session?.title }} · {{ a.status }} ·
                            {{ a.recorded_at }}
                        </p>
                    </section>
                    <section
                        v-else-if="section === 'payments'"
                        class="space-y-4"
                    >
                        <article
                            v-for="e in data.enrollments"
                            :key="e.id"
                            class="rounded-xl border bg-card p-5"
                        >
                            <h2 class="font-semibold">
                                {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }}
                            </h2>
                            <p>
                                Payé {{ money(e.total_paid) }} · Reste
                                {{ money(e.remaining_balance) }}
                            </p>
                            <div class="mt-3">
                                <p
                                    v-for="p in e.payments"
                                    :key="p.id"
                                    class="border-t py-2 text-sm"
                                >
                                    {{ p.reference }} · {{ money(p.amount) }} ·
                                    <a
                                        class="text-primary underline"
                                        :href="`/portal/payments/${p.id}/receipt`"
                                        >Reçu</a
                                    >
                                </p>
                            </div>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'documents'"
                        class="space-y-2"
                    >
                        <a
                            v-for="f in profile.files"
                            :key="f.id"
                            :href="f.url"
                            target="_blank"
                            class="block rounded-lg border p-3 text-primary"
                            >Document {{ f.id }}</a
                        >
                    </section>
                    <section v-else>
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p class="text-sm text-muted-foreground">
                                {{ n.message }}
                            </p>
                        </button>
                    </section></template
                >
                <template v-else
                    ><section
                        v-if="section === 'groups'"
                        class="grid gap-3 md:grid-cols-2"
                    >
                        <article
                            v-for="g in data.groups"
                            :key="g.id"
                            class="rounded-xl border bg-card p-5"
                        >
                            <h2 class="font-semibold">{{ g.name }}</h2>
                            <p>
                                {{ g.plan.course.title }} ·
                                {{ g.classroom?.name }}
                            </p>
                        </article>
                    </section>
                    <section
                        v-else-if="section === 'students'"
                        class="space-y-2"
                    >
                        <div
                            v-for="e in data.students"
                            :key="e.id"
                            class="rounded-lg border p-3"
                        >
                            {{ e.student.first_name }}
                            {{ e.student.last_name }} ·
                            {{ e.form?.course?.title || e.training_plan_group?.plan?.course?.title || 'Formation' }} · Groupe
                            {{ e.group_number }}
                        </div>
                    </section>
                    <section
                        v-else-if="section === 'attendance'"
                        class="grid gap-4 lg:grid-cols-2"
                    >
                        <div>
                            <button
                                v-for="s in data.sessions"
                                :key="s.id"
                                class="mb-2 block w-full rounded-lg border p-3 text-left"
                                @click="choose(s)"
                            >
                                {{ s.title }} · {{ s.starts_at }}
                            </button>
                        </div>
                        <form
                            v-if="selected"
                            class="rounded-xl border p-4"
                            @submit.prevent="saveAttendance"
                        >
                            <h2 class="font-semibold">{{ selected.title }}</h2>
                            <Button type="button" class="mt-3" variant="outline" @click="records.records.forEach((r:any)=>r.status='present')">Tous présents</Button>
                            <div
                                v-for="r in records.records"
                                :key="r.student_id"
                                class="mt-3 flex gap-2"
                            >
                                <span class="flex flex-1 items-center gap-2"><img v-if="r.photo_url" :src="r.photo_url" class="size-8 rounded-full object-cover"/>{{ r.student_name }}</span
                                ><select
                                    v-model="r.status"
                                    class="rounded border bg-background px-2"
                                >
                                    <option value="present">Présent</option>
                                    <option value="absent">Absent</option>
                                    <option value="late">Retard</option>
                                    <option value="excused">Excusé</option>
                                </select>
                            </div>
                            <Button class="mt-4">Enregistrer</Button>
                        </form>
                    </section>
                    <section
                        v-else-if="section === 'planning'"
                        class="space-y-2"
                    >
                        <div
                            v-for="s in data.sessions"
                            :key="s.id"
                            class="rounded-lg border p-4"
                        >
                            {{ s.title }} · {{ s.starts_at }} → {{ s.ends_at }}
                        </div>
                    </section>
                    <section v-else>
                        <button
                            v-for="n in data.notifications"
                            :key="n.id"
                            class="block w-full rounded-lg border p-4 text-left"
                            @click="read(n)"
                        >
                            <b>{{ n.title }}</b>
                            <p>{{ n.message }}</p>
                        </button>
                    </section></template
                >
            </div>
        </main></AdminLayout
    >
</template>
