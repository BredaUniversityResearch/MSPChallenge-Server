# Community Charter & General Agreement

## MSP Platform Suite

**Effective Date**:20 April 2026

**Current Custodian**: Breda University of Applied Sciences (BUas),
through CRADLE Lab, at Academy for AI, Games and Media

# Preamble

The MSP Platform Suite (<https://www.mspchallenge.info/>) is a family of
tools for maritime spatial planning that has been built over many years
through the collaborative effort of researchers, developers,
governments, and institutions across multiple countries and funding
programmes. It is not the product of any single organisation.

This Charter establishes the foundational principles, governance model,
and conditions under which the MSP Platform Suite is maintained,
developed, and made available. It applies to everyone who uses,
contributes to, or builds upon the platform.

By using the MSP Platform Suite and its products in any capacity —
downloading the software, participating in workshops, hosting your own
instance with any of the software products of the MSP Platform Suite, or
contributing code or data — you accept the principles and conditions set
out in this Charter.

# 1. Mission

The MSP Platform Suite exists to support the sustainable development of
the ocean and coastal areas. It is a tool for understanding, exploring,
and planning the use of marine space in a way that balances ecological
integrity with societal and economic needs.

The platform is designed to serve education, research, policy
development, and stakeholder engagement in maritime spatial planning. It
is aligned with the EU framing “sustainable blue economy”: economic
activity related to the ocean that accounts for the long-term health of
marine ecosystems.

The platform must not be used for purposes that are fundamentally
contrary to this mission, including but not limited to military
targeting, military operational support, facilitating illegal resource
extraction, or activities designed to harm marine ecosystems.

# 2. Ownership and Custodianship

### 2.1 Community Ownership

The MSP Platform Suite and its individual products are a community
asset. No single organisation, institution, or individual owns it
exclusively. The platform’s intellectual heritage belongs to the
collective of people and organisations who have contributed to its
conception, development, and growth over time — including its
originators, developers, funders, data providers, and user communities.
For a list of credits, visit
<https://community.mspchallenge.info/wiki/Credits>.

### 2.2 The Custodian Role

**Breda University of Applied Sciences (BUas)**, through its CRADLE Lab
(Centre for Research and Applied Development in Learning Environments),
currently serves as **custodian** of the MSP Platform Suite.

The custodian:

- Hosts and maintains the platform’s infrastructure, source code
  repositories, and curated data

- Coordinates development, ensures quality, and manages releases

- Acts as the operational steward of the community’s shared asset

- Upholds the principles set out in this Charter

**The custodian does not own the MSP Platform Suite.** Custodianship is
a responsibility, not a property right. BUas may claim its significant
contribution to the platform’s development and may assert intellectual
property rights over specific contributions made by its employees, but
it cannot claim sole ownership over the platform, its name, or its
community-contributed heritage.

### 2.3 Continuity and Succession

The custodian must not block the continued development and use of the
MSP Platform Suite. Specifically:

- No individual, organisation, or custodian may unilaterally withdraw,
  close, or restrict public access to the open-source components of the
  platform in a way that prevents the community of users from continuing
  its use and development. This does not limit the custodian's ability
  to enforce the **Terms & Conditions** against individual users,
  including suspension or termination of access for violations.

- If the current custodian ceases to fulfil its role — whether by
  choice, institutional change, or circumstance — the platform’s
  open-source code, documentation, and community resources must remain
  available for another qualified entity to assume custodianship.

- The open-source code repositories (currently hosted on GitHub for each
  of the software products of the MSP Platform Suite) serve as the
  platform’s continuity anchor. It must always be maintained as a fork
  able, community-accessible resource.

- Such repositories are, but not limited to:

  - <https://github.com/BredaUniversityResearch/MSPChallenge-Simulations>

  - <https://github.com/BredaUniversityResearch/MSP-ProceduralOceanView>

  - <https://github.com/BredaUniversityResearch/MSPChallenge-Server>

  - <https://github.com/BredaUniversityResearch/MSPChallenge-Simulation-Example>

  - <https://github.com/BredaUniversityResearch/MSPChallenge-Client>

- Any successor custodian must accept and uphold this Charter.

### 2.4 Contributor Rights

Contributors to the platform — whether as developers, researchers, data
providers, or funders — are acknowledged and credited. No contributor
may claim sole ownership of the platform by virtue of their
contribution, and no contributor may block the platform’s continued
development after their involvement ends.

# 3. Open-Source Commitment and Asset Classification

### 3.1 Dual-Asset Model

The MSP Platform Suite operates under a dual-asset model that separates
open-source software from other components:

| Open-Source (AGPL-3.0 unless otherwise stated) | Restricted / Not Included |
|----|----|
| Source code for client and server applications | Curated geodata (third-party data processed for use in the platform) |
| Documentation and technical specifications | Authentication credentials and API access keys |
| API schemas and interfaces | Third-party proprietary software assets (e.g. paid water/sky rendering systems) |
| Data processing pipelines and tooling | Custodian's server infrastructure and hosted services |

### 3.2 What Open-Source Means Here

Anyone may view, modify, and redistribute the open-source code under the
terms of its licence (AGPL-3.0 or as explicitly stated per component).
This does not include the right to access the custodian’s servers,
curated data, or third-party proprietary assets.

Where the open-source software depends on third-party proprietary
components for full functionality (e.g. the Immersive Ocean’s water
rendering system), users are informed that the open-source distribution
is not 100% functional without those components. The proprietary
components are clearly identified, and it is the user’s responsibility
to acquire them independently.

### 3.3 The MSP Platform Suite

The MSP Platform Suite includes, but can go beyond, the current
following set of products:

- MSP Challenge Simulation Platform

- Immersive Ocean

- Procedural Ocean

- GeoViz

- AugGIS

The software products and tools of the MSP Platform Suite are governed
by this Charter.

# 4. Data Governance Principles

### 4.1 The Custodian Is Not a Data Provider

The custodian curates and integrates geodata from third-party sources
(under different licences) for use within the MSP Platform Suite. The
custodian does not own this data and does not act as a data provider.
Users of the platform may not treat data encountered within the platform
as data sourced from the custodian.

### 4.2 Data Sources and Licensing

Data used in the platform originates from public and institutional
sources (e.g. Copernicus, EMODnet, national agencies) and from project
partners. Each dataset carries its original licence conditions. The
custodian documents the data sources at
[community.mspchallenge.info/wiki/Data_sources](https://community.mspchallenge.info/wiki/Data_sources)
and makes reasonable efforts to respect upstream licence terms.

Users who need access to the underlying data must obtain it directly
from the original data providers under their own licence terms.

### 4.3 Data Accuracy Disclaimer

Data within the MSP Platform Suite is curated for the purpose of
interactive simulation and exploration. It is not certified, not
guaranteed to be current, and not suitable for operational
decision-making, navigation, or real-world planning without independent
verification. The custodian makes no warranties regarding the accuracy,
completeness, or fitness of any data for any purpose. Full warranty
disclaimers are set out in the Terms & Conditions.

# 5. Service Model

The MSP Platform Suite is free to use. The custodian does not charge for
the software or for the data. Revenue, when existing, comes from
services: facilitating workshops, providing expert consultation,
developing custom editions or features, offering training, and
supporting deployments.

This service model is compatible with the open-source commitment. The
platform remains free; the expertise to apply it effectively is what
carries value.

Third parties are equally free to offer services around the platform,
provided they respect this Charter, credit the community’s work, and do
not claim the platform as their proprietary product.

# 6. Conditions for All Users

By using the MSP Platform Suite or any of its products, you agree to the
following:

- **Respect the mission.** Use the platform for purposes aligned with
  sustainable development of the ocean, research, education, or
  legitimate policy exploration.

- **Do not extract data.** Data displayed within the platform is for
  in-platform use only. You may not scrape, extract, download, or
  reverse-engineer data from the platform’s interfaces or backend
  services.

- **Credit the community.** When referring to the MSP Platform Suite or
  any of its products in publications, presentations, or public
  communications, acknowledge the platform and its community of
  contributors.

- **Do not misrepresent.** Do not present the platform, its data, or its
  outputs as your own proprietary product. Do not imply endorsement by
  the custodian or the community without agreement.

- **Accept AS-IS provision.** The platform is provided as-is, without
  warranty. You use it at your own risk.

- **Respect upstream licences.** If you interact with data or code
  components, respect the licence terms that apply to each.

Additional terms apply depending on how you use the platform. See the
<a href="B_Terms_and_Conditions.md">**Terms & Conditions for Users of the MSP Platform Suite** (Document B)</a>
for the full terms governing software use, the **Data Sharing
Agreement** (Document C) for partnerships involving data contributions,
and the **Session Agreement** (Document D) for workshops and sessions
using the software products of MSP Platform Suite.

# 7. Governance and Amendments

### 7.1 Charter Amendments

This Charter may be amended by the custodian in consultation with the
community. Significant changes — particularly to Sections 1 (Mission), 2
(Ownership and Custodianship), and 3 (Open-Source Commitment) — should
be communicated publicly and allow reasonable time for community
feedback before taking effect.

### 7.2 Custodian Accountability

The custodian is the first party bound by this Charter. It commits to
acting in good faith, in the interest of the platform's community and
mission, and to upholding the principles set out in this document.

The custodian retains the right to enforce the Terms & Conditions
against individual users, including suspending or terminating access for
users who breach those Terms, violate this Charter, or use the platform
for purposes contrary to its mission.

The open-source licence (AGPL-3.0) inherently permits anyone to fork and
redistribute the source code. This is a property of the license itself,
not a right granted by this Charter. However, the custodian's
infrastructure, curated data, branding, and trademarks are not included
in any fork. Any party that forks or builds upon the platform's
open-source code is expected to respect the mission and principles of
this Charter, even though the custodian cannot enforce this expectation
beyond the terms of the AGPL-3.0 licence.

If the community believes the custodian is acting contrary to this
Charter, the open-source nature of the code ensures that the platform
cannot be held hostage by any single organisation. The community's
ability to continue development independently is the ultimate safeguard
of the platform's continuity — not a mechanism for circumventing the
Charter's principles.

# 8. Governing Law

This Charter and all matters arising from the use of the MSP Platform
Suite are governed by the laws of the Netherlands. Disputes shall be
subject to the jurisdiction of the courts of the Netherlands.

# 9. Document Hierarchy

The MSP Platform Suite’s legal framework consists of four documents:

<table>
<colgroup>
<col style="width: 16%" />
<col style="width: 36%" />
<col style="width: 47%" />
</colgroup>
<thead>
<tr>
<th>Document</th>
<th>Purpose</th>
<th>Applies To</th>
</tr>
</thead>
<tbody>
<tr>
<td>A. This Charter</td>
<td style="text-align: left;">Mission, governance, ownership model,
foundational principles</td>
<td>Everyone: users, contributors, partners, custodian</td>
</tr>
<tr>
<td><a href="B_Terms_and_Conditions.md">B. Terms &amp; Conditions</a></td>
<td style="text-align: left;">Specific rules for using the software and
accessing services</td>
<td>All users of the MSP Platform Suite (software products, workshop
participation, and hosted instances).</td>
</tr>
<tr>
<td><a href="C_Data_Sharing_Agreement.md">C. Data Sharing Agreement</a></td>
<td>Terms for partners who contribute data to the platform</td>
<td>Project partners and data contributors only</td>
</tr>
<tr>
<td><a href="D_Session_Agreement.md">D. Session Agreement</a></td>
<td>This is an agreement between the custodian (BUas) and the
commissioning party</td>
<td><p>The entity on whose behalf or at whose initiative the session is
being organized.</p>
<p>One example is: <strong>Commissioned workshops</strong> — an external
entity contracts BUas to run a workshop.</p>
<p>Another example is: <strong>Project workshops</strong> — BUas is in a
funded R&amp;D project together with partners, and running workshops is
part of the project deliverables. The partners <em>are</em> the
participants. BUas organizes and runs the session.</p></td>
</tr>
</tbody>
</table>

In case of conflict, this Charter (Document A) takes precedence.

Document Control

Version: 5.0 — Draft for Review

Last Updated: 20 April 2026

Maintained by: Breda University of Applied Sciences

Project website: <https://www.mspchallenge.info/>

Contact: <contact@mspchallenge.info>
