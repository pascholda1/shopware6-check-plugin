import './field-bind-switch.scss';

const {Component} = Shopware;
import template from './field-bind-switch.html.twig';

Component.register('field-bind-switch', {
  template,
  model: {
    prop: 'value',
    event: 'change',
  },
  props: {
    value: {
      type: Boolean,
      required: false,
      // TODO: Boolean props should only be opt in and therefore default to false
      // eslint-disable-next-line vue/no-boolean-default
      default: null,
    },

    inheritedValue: {
      type: Boolean,
      required: false,
      // TODO: Boolean props should only be opt in and therefore default to false
      // eslint-disable-next-line vue/no-boolean-default
      default: null,
    },

    ghostValue: {
      type: Boolean,
      required: false,
      // TODO: Boolean props should only be opt in and therefore default to false
      // eslint-disable-next-line vue/no-boolean-default
      default: null,
    },

    error: {
      type: Object,
      required: false,
      default: null,
    },

    bordered: {
      type: Boolean,
      required: false,
      default: false,
    },
  },
  created() {
    window.$mySwitch = this;
  },
  computed: {
    shouldDisable() {
      const fieldsFalse = this.$attrs.disabledWhenFalse
          ?.split(',')
          .map(field => this.getFieldName(field)) ?? [];

      return this.$attrs.myConfig.elements
          .filter(({name}) => fieldsFalse.find(n => name === n))
          .some(({name}) => !this.$attrs.actualConfigData[name]);
    },
    label() {
      return this.$parent.$parent.label;
    },
  },
  methods: {
    getFieldName(field) {
      return ['AdresslaborCheckPlugin', 'config', field].join('.');
    },
  },
});
