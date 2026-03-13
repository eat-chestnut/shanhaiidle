# Equipment Stat Aggregator Module

## 模块边界

- 本模块只负责战斗前属性汇总。
- 本轮不处理 controller / API / UI 渲染。
- 本轮不处理最终伤害公式、乘区折算、Buff 时序、DOT / HOT、触发执行、掉落结算。
- `special_effects` 只做登记，不做触发、不做持续时间、不做叠层、不做执行。

## 统一返回结构

4 个聚合器的公开方法统一返回：

```json
{
  "ok": true,
  "reason": null,
  "data": {}
}
```

失败时统一返回：

```json
{
  "ok": false,
  "reason": "具体失败原因",
  "data": null
}
```

## 四个聚合器职责

### BaseEquipmentStatAggregator

- 只汇总装备本体白字与固定基础值。
- 允许汇总：
  - 套装成品本体基础属性
  - 蓝装模板白字
  - 装备本体白字
  - 其他直接写在装备本体上的固定基础值
- 不允许混入：
  - 套装件数效果
  - 护符效果
  - 宝石效果
  - Boss 核心效果
  - 蓝词条效果
- `data` 仅返回基础属性对象，例如 `MELEE_ATK / HP / DEF / ATK_SPEED`。

### EquipmentBonusAggregator

- 只汇总标准数值型加成。
- 允许汇总：
  - 套装件数效果
  - 护符基础效果
  - 护符星级连锁效果
  - 宝石效果
  - Boss 核心数值效果
  - 蓝词条数值效果
- 聚合规则：
  - 按 `effect_key` 聚合
  - 保留 `value_type / value / source`
  - 不做最终战斗折算
  - 不做上限处理

### EquipmentSpecialEffectAggregator

- 只收集特殊效果登记项。
- 允许登记：
  - 护符触发型效果
  - Boss 核心特殊效果
  - 套装特殊效果
  - 特殊蓝词条效果
- 输出统一为列表，每项至少包含：
  - `effect_key`
  - `source`
- 这里不执行效果，只为后续战斗层保留可追踪输入。

### PlayerEquipmentStatAggregator

- 作为总聚合入口，统一组合前三个聚合器的结果。
- 最终 `data` 固定包含：
  - `base_stats`
  - `bonus_stats`
  - `special_effects`
  - `sources`
- 当前实现只读取现有玩家实例层已落库的数据源；当前 schema 没有玩家 Boss Core / 蓝词条实例挂载表时，相关结果返回空集合，不提供 demo fallback 数据。

## 四个输出区块的边界

### base_stats

- 只放基础白字和固定基础值。
- 这里的值已经按同名 `stat_key` 求和。
- 不放套装件数、护符、宝石、Boss 核心、蓝词条数值加成。

### bonus_stats

- 只放标准数值型加成。
- 每个 `effect_key` 对应一个列表，列表内保留不同来源的原始条目。
- 这里保留原始 `value_type`，不做最终乘区换算。

### special_effects

- 只登记特殊效果，不执行。
- 这里只保留后续战斗层需要识别的 `effect_key` 与 `source`。

### sources

- 记录聚合过程中实际参与的来源明细。
- 当前至少支持这些来源类型：
  - `equipment`
  - `set_effect`
  - `talisman_base`
  - `talisman_star_link`
  - `gem`
  - `boss_core`
  - `blue_affix`

## 为什么保留 sources

- 方便排查某个数值是由哪件装备、哪条套装效果、哪颗宝石带来的。
- 方便后续战斗公式层只消费聚合结果，不反向耦合装备明细查询。
- 方便后续补充 Boss Core、蓝词条、护符触发逻辑时做回归验证。
- 方便测试直接校验来源链路是否完整，而不是只看最终总值。
