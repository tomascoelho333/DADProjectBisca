<template>
  <div class="admin-container">
    <div class="admin-header">
      <h1>Administration Dashboard</h1>
      <div class="admin-nav">
        <button 
          v-for="tab in tabs" 
          :key="tab"
          :class="['nav-btn', { active: activeTab === tab }]"
          @click="activeTab = tab"
        >
          {{ formatTabName(tab) }}
        </button>
      </div>
    </div>

    <!-- Platform Statistics Tab -->
    <div v-if="activeTab === 'statistics'" class="tab-content">
      <div class="stats-container">
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Total Users</h3>
            <p class="stat-value">{{ stats.user_statistics?.total_users || 0 }}</p>
            <p class="stat-subtitle">
              Players: {{ stats.user_statistics?.total_players || 0 }} | 
              Admins: {{ stats.user_statistics?.total_admins || 0 }}
            </p>
          </div>
          <div class="stat-card">
            <h3>Blocked Users</h3>
            <p class="stat-value">{{ stats.user_statistics?.blocked_users || 0 }}</p>
            <p class="stat-subtitle">Deleted: {{ stats.user_statistics?.deleted_users || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Total Games</h3>
            <p class="stat-value">{{ stats.game_statistics?.total_games || 0 }}</p>
            <p class="stat-subtitle">Matches: {{ stats.game_statistics?.total_matches || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Games Playing</h3>
            <p class="stat-value">{{ stats.game_statistics?.games_playing || 0 }}</p>
            <p class="stat-subtitle">Avg Duration: {{ stats.game_statistics?.avg_game_duration_seconds || 0 }}s</p>
          </div>
          <div class="stat-card">
            <h3>Total Revenue</h3>
            <p class="stat-value">€{{ stats.financial_statistics?.total_revenue_euros || 0 }}</p>
            <p class="stat-subtitle">Purchases: {{ stats.financial_statistics?.total_purchases || 0 }}</p>
          </div>
          <div class="stat-card">
            <h3>Coins Circulation</h3>
            <p class="stat-value">{{ stats.financial_statistics?.total_coins_circulation || 0 }}</p>
            <p class="stat-subtitle">Avg per Player: {{ stats.financial_statistics?.avg_spend_per_player_euros || 0 }}€</p>
          </div>
        </div>
      </div>
    </div>

    <!-- User Management Tab -->
    <div v-if="activeTab === 'users'" class="tab-content">
      <div class="users-management">
        <div class="controls">
          <input 
            v-model="userSearch" 
            type="text" 
            placeholder="Search by name, email, or nickname"
            class="search-input"
          />
          <div class="filter-buttons">
            <button 
              :class="['filter-btn', { active: userTypeFilter === 'all' }]"
              @click="userTypeFilter = 'all'"
            >
              All Users
            </button>
            <button 
              :class="['filter-btn', { active: userTypeFilter === 'P' }]"
              @click="userTypeFilter = 'P'"
            >
              Players Only
            </button>
            <button 
              :class="['filter-btn', { active: userTypeFilter === 'A' }]"
              @click="userTypeFilter = 'A'"
            >
              Admins Only
            </button>
          </div>
        </div>

        <div class="users-table">
          <table>
            <thead>
              <tr>
                <th>Nickname</th>
                <th>Email</th>
                <th>Type</th>
                <th>Blocked</th>
                <th>Coins</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in filteredUsers" :key="user.id">
                <td>{{ user.nickname }}</td>
                <td>{{ user.email }}</td>
                <td><span :class="['badge', user.type === 'A' ? 'admin' : 'player']">{{ user.type === 'A' ? 'Admin' : 'Player' }}</span></td>
                <td><span :class="['badge', user.blocked ? 'blocked' : 'active']">{{ user.blocked ? 'Blocked' : 'Active' }}</span></td>
                <td>{{ user.coins_balance }}</td>
                <td><span :class="['badge', user.deleted_at ? 'deleted' : 'active']">{{ user.deleted_at ? 'Deleted' : 'Active' }}</span></td>
                <td class="actions">
                  <button class="action-btn info" @click="viewUserDetails(user.id)" title="View Details">📋</button>
                  <button 
                    v-if="user.type === 'P'" 
                    :class="['action-btn', user.blocked ? 'success' : 'warning']" 
                    @click="toggleBlockUser(user)" 
                    :title="user.blocked ? 'Unblock' : 'Block'"
                  >
                    {{ user.blocked ? '🔓' : '🔒' }}
                  </button>
                  <button 
                    v-if="user.type !== 'A' || !isOwnAccount(user.id)"
                    class="action-btn danger" 
                    @click="deleteUser(user)" 
                    title="Delete"
                  >
                    🗑️
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- User Details Modal -->
        <div v-if="selectedUserDetails" class="modal-overlay" @click="selectedUserDetails = null">
          <div class="modal-content" @click.stop>
            <button class="modal-close" @click="selectedUserDetails = null">×</button>
            <h2>User Details</h2>
            <div class="user-details">
              <div class="detail-section">
                <h3>Profile</h3>
                <p><strong>Name:</strong> {{ selectedUserDetails.user?.name }}</p>
                <p><strong>Email:</strong> {{ selectedUserDetails.user?.email }}</p>
                <p><strong>Nickname:</strong> {{ selectedUserDetails.user?.nickname }}</p>
                <p><strong>Type:</strong> {{ selectedUserDetails.user?.type === 'A' ? 'Administrator' : 'Player' }}</p>
                <p><strong>Blocked:</strong> {{ selectedUserDetails.user?.blocked ? 'Yes' : 'No' }}</p>
                <p><strong>Created:</strong> {{ formatDate(selectedUserDetails.user?.created_at) }}</p>
              </div>
              <div class="detail-section">
                <h3>Statistics</h3>
                <p><strong>Coins Balance:</strong> {{ selectedUserDetails.user?.coins_balance }}</p>
                <p><strong>Games Played:</strong> {{ selectedUserDetails.statistics?.total_games_played }}</p>
                <p><strong>Wins:</strong> {{ selectedUserDetails.statistics?.total_wins }}</p>
                <p><strong>Win Rate:</strong> {{ selectedUserDetails.statistics?.win_rate }}%</p>
                <p><strong>Transactions:</strong> {{ selectedUserDetails.statistics?.total_transactions }}</p>
                <p><strong>Total Spent:</strong> €{{ selectedUserDetails.statistics?.total_spent_euros }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Admin Tab -->
    <div v-if="activeTab === 'create-admin'" class="tab-content">
      <div class="create-admin-form">
        <h2>Create New Administrator Account</h2>
        <form @submit.prevent="submitCreateAdmin">
          <div class="form-group">
            <label for="admin-name">Full Name</label>
            <input v-model="newAdminForm.name" id="admin-name" type="text" required />
          </div>
          <div class="form-group">
            <label for="admin-email">Email</label>
            <input v-model="newAdminForm.email" id="admin-email" type="email" required />
          </div>
          <div class="form-group">
            <label for="admin-nickname">Nickname</label>
            <input v-model="newAdminForm.nickname" id="admin-nickname" type="text" max="20" required />
          </div>
          <div class="form-group">
            <label for="admin-password">Password</label>
            <input v-model="newAdminForm.password" id="admin-password" type="password" min="3" required />
          </div>
          <div class="form-group">
            <label for="admin-password-confirm">Confirm Password</label>
            <input v-model="newAdminForm.password_confirmation" id="admin-password-confirm" type="password" required />
          </div>
          <div class="form-group">
            <label for="admin-photo">Profile Photo (Optional)</label>
            <input id="admin-photo" type="file" accept="image/*" @change="(event) => { newAdminForm.photo_avatar_filename = event.target.files?.[0] }" />
          </div>
          <div v-if="createAdminMessage" :class="['message', createAdminMessageType]">
            {{ createAdminMessage }}
          </div>
          <button type="submit" class="btn btn-primary">Create Administrator</button>
        </form>
      </div>
    </div>

    <!-- Transactions Tab -->
    <div v-if="activeTab === 'transactions'" class="tab-content">
      <div class="transactions-view">
        <h2>All Transactions</h2>
        <div class="transactions-table">
          <table>
            <thead>
              <tr>
                <th>Date</th>
                <th>User</th>
                <th>Type</th>
                <th>Coins</th>
                <th>Game</th>
                <th>Match</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="transaction in transactions" :key="transaction.id">
                <td>{{ formatDate(transaction.transaction_datetime) }}</td>
                <td>{{ transaction.user?.nickname }}</td>
                <td>{{ transaction.type_name }}</td>
                <td :class="{ positive: transaction.coins > 0, negative: transaction.coins < 0 }">
                  {{ transaction.coins > 0 ? '+' : '' }}{{ transaction.coins }}
                </td>
                <td>{{ transaction.game_id || '-' }}</td>
                <td>{{ transaction.match_id || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Matches Tab -->
    <div v-if="activeTab === 'matches'" class="tab-content">
      <div class="matches-view">
        <h2>All Matches</h2>
        <div class="matches-table">
          <table>
            <thead>
              <tr>
                <th>Match ID</th>
                <th>Type</th>
                <th>Player 1</th>
                <th>Player 2</th>
                <th>Status</th>
                <th>Stake</th>
                <th>Result</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="match in matches" :key="match.id">
                <td>{{ match.id }}</td>
                <td>Bisca dos {{ match.type }}</td>
                <td>{{ match.player1_nickname }}</td>
                <td>{{ match.player2_nickname }}</td>
                <td><span :class="['badge', getStatusClass(match.status)]">{{ match.status }}</span></td>
                <td>{{ match.stake }}</td>
                <td>{{ match.winner_user_id ? 'Ended' : 'In Progress' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { useUserStore } from '@/stores/user';

export default {
  name: 'AdminView',
  data() {
    return {
      activeTab: 'statistics',
      tabs: ['statistics', 'users', 'create-admin', 'transactions', 'matches'],
      stats: {},
      users: [],
      filteredUsers: [],
      userSearch: '',
      userTypeFilter: 'all',
      selectedUserDetails: null,
      transactions: [],
      matches: [],
      newAdminForm: {
        name: '',
        email: '',
        nickname: '',
        password: '',
        password_confirmation: '',
        photo_avatar_filename: null,
      },
      createAdminMessage: '',
      createAdminMessageType: '',
      loading: false,
    };
  },
  computed: {
    userStore() {
      return useUserStore();
    },
  },
  mounted() {
    this.checkAdminAccess();
    this.loadPlatformStats();
    this.loadUsers();
    this.loadTransactions();
    this.loadMatches();
  },
  methods: {
    checkAdminAccess() {
      if (!this.userStore.user || this.userStore.user.type !== 'A') {
        this.$router.push('/dashboard');
      }
    },
    async loadPlatformStats() {
      try {
        const response = await fetch('/api/admin/platform-stats', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        this.stats = await response.json();
      } catch (error) {
        console.error('Failed to load platform stats:', error);
      }
    },
    async loadUsers() {
      try {
        const response = await fetch('/api/admin/users?per_page=100', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        const data = await response.json();
        this.users = data.data || [];
        this.filterUsers();
      } catch (error) {
        console.error('Failed to load users:', error);
      }
    },
    filterUsers() {
      this.filteredUsers = this.users.filter((user) => {
        const matchesSearch =
          user.nickname.toLowerCase().includes(this.userSearch.toLowerCase()) ||
          user.email.toLowerCase().includes(this.userSearch.toLowerCase()) ||
          user.name.toLowerCase().includes(this.userSearch.toLowerCase());

        const matchesType =
          this.userTypeFilter === 'all' || user.type === this.userTypeFilter;

        return matchesSearch && matchesType;
      });
    },
    async viewUserDetails(userId) {
      try {
        const response = await fetch(`/api/admin/users/${userId}`, {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        this.selectedUserDetails = await response.json();
      } catch (error) {
        console.error('Failed to load user details:', error);
      }
    },
    async toggleBlockUser(user) {
      try {
        const response = await fetch(`/api/admin/users/${user.id}/toggle-block`, {
          method: 'PUT',
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        if (response.ok) {
          user.blocked = !user.blocked;
        }
      } catch (error) {
        console.error('Failed to toggle block:', error);
      }
    },
    async deleteUser(user) {
      if (!confirm(`Are you sure you want to delete ${user.nickname}?`)) return;

      try {
        const response = await fetch(`/api/admin/users/${user.id}`, {
          method: 'DELETE',
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        if (response.ok) {
          this.users = this.users.filter((u) => u.id !== user.id);
          this.filterUsers();
        }
      } catch (error) {
        console.error('Failed to delete user:', error);
      }
    },
    async submitCreateAdmin() {
      this.loading = true;
      this.createAdminMessage = '';

      try {
        const response = await fetch('/api/admin/create-admin', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Authorization: `Bearer ${localStorage.getItem('token')}`,
          },
          body: JSON.stringify(this.newAdminForm),
        });

        if (response.ok) {
          this.createAdminMessage = 'Administrator account created successfully!';
          this.createAdminMessageType = 'success';
          this.newAdminForm = {
            name: '',
            email: '',
            nickname: '',
            password: '',
            password_confirmation: '',
            photo_avatar_filename: null,
          };
          this.loadUsers();
        } else {
          const error = await response.json();
          this.createAdminMessage = error.message || 'Failed to create admin account';
          this.createAdminMessageType = 'error';
        }
      } catch (error) {
        this.createAdminMessage = 'An error occurred: ' + error.message;
        this.createAdminMessageType = 'error';
      } finally {
        this.loading = false;
      }
    },
    async loadTransactions() {
      try {
        const response = await fetch('/api/admin/transactions?per_page=50', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        const data = await response.json();
        this.transactions = data.data || [];
      } catch (error) {
        console.error('Failed to load transactions:', error);
      }
    },
    async loadMatches() {
      try {
        const response = await fetch('/api/admin/matches?per_page=50', {
          headers: { Authorization: `Bearer ${localStorage.getItem('token')}` },
        });
        const data = await response.json();
        this.matches = data.data || [];
      } catch (error) {
        console.error('Failed to load matches:', error);
      }
    },
    isOwnAccount(userId) {
      return this.userStore.user?.id === userId;
    },
    formatDate(dateString) {
      if (!dateString) return 'N/A';
      return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    },
    formatTabName(tab) {
      return tab
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },
    getStatusClass(status) {
      const classes = {
        Pending: 'pending',
        Playing: 'playing',
        Ended: 'ended',
        Interrupted: 'interrupted',
      };
      return classes[status] || 'unknown';
    },
  },
  watch: {
    userSearch() {
      this.filterUsers();
    },
    userTypeFilter() {
      this.filterUsers();
    },
  },
};
</script>

<style scoped>
.admin-container {
  max-width: 1400px;
  margin: 0 auto;
  padding: 20px;
}

.admin-header {
  margin-bottom: 30px;
  border-bottom: 2px solid #e0e0e0;
  padding-bottom: 20px;
}

.admin-header h1 {
  color: #333;
  margin: 0 0 20px 0;
}

.admin-nav {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.nav-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 4px;
  background: #f5f5f5;
  color: #666;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s ease;
}

.nav-btn.active {
  background: #4CAF50;
  color: white;
}

.nav-btn:hover {
  background: #e0e0e0;
}

.nav-btn.active:hover {
  background: #45a049;
}

.tab-content {
  background: white;
  padding: 30px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Statistics */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 30px;
}

.stat-card {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.stat-card:nth-child(2) {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card:nth-child(3) {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card:nth-child(4) {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-card:nth-child(5) {
  background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-card:nth-child(6) {
  background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
}

.stat-card h3 {
  margin: 0 0 10px 0;
  font-size: 14px;
  opacity: 0.9;
}

.stat-value {
  font-size: 32px;
  font-weight: bold;
  margin: 10px 0;
}

.stat-subtitle {
  font-size: 12px;
  opacity: 0.8;
  margin: 5px 0 0 0;
}

/* User Management */
.controls {
  margin-bottom: 20px;
}

.search-input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  margin-bottom: 10px;
  box-sizing: border-box;
}

.filter-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-btn {
  padding: 8px 16px;
  border: 1px solid #ddd;
  border-radius: 4px;
  background: white;
  color: #666;
  cursor: pointer;
  transition: all 0.3s ease;
}

.filter-btn.active {
  background: #4CAF50;
  color: white;
  border-color: #4CAF50;
}

.users-table,
.transactions-table,
.matches-table {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 20px;
}

thead {
  background: #f5f5f5;
}

th {
  padding: 12px;
  text-align: left;
  font-weight: 600;
  color: #333;
  border-bottom: 2px solid #ddd;
}

td {
  padding: 12px;
  border-bottom: 1px solid #eee;
}

tbody tr:hover {
  background: #f9f9f9;
}

.badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.badge.admin {
  background: #e3f2fd;
  color: #1976d2;
}

.badge.player {
  background: #f3e5f5;
  color: #7b1fa2;
}

.badge.active {
  background: #e8f5e9;
  color: #2e7d32;
}

.badge.blocked {
  background: #ffebee;
  color: #c62828;
}

.badge.deleted {
  background: #fce4ec;
  color: #ad1457;
}

.badge.pending {
  background: #fff3e0;
  color: #e65100;
}

.badge.playing {
  background: #e0f2f1;
  color: #00695c;
}

.badge.ended {
  background: #e8f5e9;
  color: #2e7d32;
}

.badge.interrupted {
  background: #ede7f6;
  color: #4527a0;
}

.actions {
  display: flex;
  gap: 5px;
}

.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.action-btn.info {
  background: #e3f2fd;
  color: #1976d2;
}

.action-btn.warning {
  background: #fff3e0;
  color: #e65100;
}

.action-btn.success {
  background: #e8f5e9;
  color: #2e7d32;
}

.action-btn.danger {
  background: #ffebee;
  color: #c62828;
}

.action-btn:hover {
  opacity: 0.7;
}

/* Modal */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal-content {
  background: white;
  padding: 30px;
  border-radius: 8px;
  max-width: 500px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
  position: relative;
}

.modal-close {
  position: absolute;
  top: 10px;
  right: 10px;
  background: none;
  border: none;
  font-size: 28px;
  cursor: pointer;
  color: #999;
}

.user-details {
  margin-top: 20px;
}

.detail-section {
  margin-bottom: 20px;
}

.detail-section h3 {
  margin-top: 0;
  color: #4CAF50;
  border-bottom: 1px solid #eee;
  padding-bottom: 10px;
}

.detail-section p {
  margin: 8px 0;
  color: #666;
}

.detail-section strong {
  color: #333;
}

/* Create Admin Form */
.create-admin-form {
  max-width: 600px;
}

.create-admin-form h2 {
  color: #333;
  margin-bottom: 20px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #333;
  font-weight: 600;
}

.form-group input {
  width: 100%;
  padding: 12px;
  border: 1px solid #ddd;
  border-radius: 4px;
  font-size: 14px;
  box-sizing: border-box;
  transition: border-color 0.3s ease;
}

.form-group input:focus {
  outline: none;
  border-color: #4CAF50;
  box-shadow: 0 0 4px rgba(76, 175, 80, 0.3);
}

.message {
  padding: 12px;
  border-radius: 4px;
  margin-bottom: 20px;
  font-weight: 500;
}

.message.success {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #4caf50;
}

.message.error {
  background: #ffebee;
  color: #c62828;
  border: 1px solid #f44336;
}

.btn {
  padding: 12px 24px;
  border: none;
  border-radius: 4px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-primary {
  background: #4CAF50;
  color: white;
}

.btn-primary:hover {
  background: #45a049;
}

.btn-primary:disabled {
  background: #ccc;
  cursor: not-allowed;
}

/* Table value styling */
.positive {
  color: #4caf50;
  font-weight: 600;
}

.negative {
  color: #f44336;
  font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
  .admin-nav {
    flex-direction: column;
  }

  .nav-btn {
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .filter-buttons {
    flex-direction: column;
  }

  .filter-btn {
    width: 100%;
  }

  table {
    font-size: 12px;
  }

  th,
  td {
    padding: 8px;
  }

  .actions {
    flex-wrap: wrap;
  }
}
</style>
