<!--
  Matomo - free/libre analytics platform
  @link    https://matomo.org
  @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
-->

<template>
  <div class='teams'>
    <Field
        uicontrol="text"
        name="webhookURL"
        :title="translate('MicrosoftTeams_TeamsWebhookUrl')"
        class="teams"
        :model-value="modelValue"
        :disabled="!isRequiredFieldsSet"
        @update:model-value="$emit('update:modelValue', $event)"
    >
      <template v-slot:inline-help>
        <div id="teamsWebhookUrlHelp" class="inline-help-node">
          <span
              v-if="!isRequiredFieldsSet"
              style="margin-right:3.5px"
              v-html="$sanitize(getTeamsRequiredFieldNotSetHelpText)"
          >
          </span>
          <span
              v-else
              v-html="$sanitize(getTeamsWebhookUrlHelpText)"
          >
          </span>
        </div>
      </template>
    </Field>
  </div>
</template>

<script lang="ts">
import { defineComponent } from 'vue';
import { MatomoUrl, translate } from 'CoreHome';
import { Field } from 'CorePluginsAdmin';

export default defineComponent({
  props: {
    modelValue: String,
    isRequiredFieldsSet: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:modelValue'],
  components: {
    Field,
  },
  methods: {
    linkTo(params: QueryParameters) {
      return `?${MatomoUrl.stringify({
        ...MatomoUrl.urlParsed.value,
        ...params,
      })}`;
    },
  },
  computed: {
    getTeamsRequiredFieldNotSetHelpText() {
      const link = this.linkTo({ module: 'CoreAdminHome', action: 'generalSettings', updated: null });
      return translate(
        'MicrosoftTeams_RequiredFieldsNotSet',
        `<a href="${link}#/MicrosoftTeams" rel="noreferrer noopener" target="_blank">`,
        '</a>',
      );
    },
    getTeamsWebhookUrlHelpText() {
      const link = 'https://matomo.org/faq/reports/how-to-get-microsoft-teams-webhook-url/';
      return translate(
        'MicrosoftTeams_TeamsEnterYourWebhookUrlText',
        `<a href="${link}" rel="noreferrer noopener" target="_blank">`,
        '</a>',
      );
    },
  },
});
</script>
